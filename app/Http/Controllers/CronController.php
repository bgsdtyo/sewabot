<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronController extends Controller
{
    /**
     * Check expired subscriptions and suspend associated telegram bots.
     */
    public function checkSubscriptions(Request $request, TelegramBotService $telegramBotService): JsonResponse
    {
        $this->pollPendingOtpQuietly();
        try {
            app(TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable) {
        }

        $expiredSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('telegramBot')
            ->get();

        $expiredSubCount = 0;
        $suspendedBotCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expiredSubCount++;

            if ($subscription->telegramBot) {
                // Check if this bot has any other active, valid subscription
                $hasOtherActive = $subscription->telegramBot->subscriptions()
                    ->where('id', '!=', $subscription->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->exists();

                if (! $hasOtherActive) {
                    $telegramBotService->deactivate($subscription->telegramBot);
                    $suspendedBotCount++;
                }
            }
        }

        // Also ensure any bot currently marked 'active' without ANY active subscription is suspended
        $botsWithoutActiveSub = TelegramBot::query()
            ->where('status', 'active')
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->where('status', 'active')
                    ->where('expires_at', '>', now());
            })
            ->get();

        foreach ($botsWithoutActiveSub as $bot) {
            $telegramBotService->deactivate($bot);
            $suspendedBotCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengecekan masa aktif subscription berhasil dijalankan.',
            'expired_subscriptions' => $expiredSubCount,
            'suspended_bots' => $suspendedBotCount,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
    }

    /**
     * Check & update provider API balance for all bots and send low-balance alert to Telegram admins if configured.
     */
    public function checkProviderBalance(
        Request $request,
        TelegramBotService $telegramBotService,
        \App\Services\OtpProviderClient $client
    ): JsonResponse {
        $this->pollPendingOtpQuietly();
        try {
            app(TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable) {
        }

        $bots = TelegramBot::query()
            ->whereNotNull('otp_api_key')
            ->where('otp_api_key', '!=', '')
            ->get();

        $checked = 0;
        $alertsSent = 0;
        $results = [];

        foreach ($bots as $bot) {
            $res = $telegramBotService->checkAndAlertProviderBalance($bot, $client);
            $checked++;
            if (! empty($res['alert_sent'])) {
                $alertsSent++;
            }
            $results[] = $res;
        }

        return response()->json([
            'success' => true,
            'message' => 'Update saldo API provider untuk semua bot berhasil disinkronkan.',
            'bots_checked' => $checked,
            'alerts_sent' => $alertsSent,
            'results' => $results,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
    }

    /**
     * Sync and update latest services (Kopken / WhatsApp) stock & pricing in real-time.
     */
    public function syncStock(
        Request $request,
        \App\Services\OtpProviderClient $providerClient
    ): JsonResponse {
        $this->pollPendingOtpQuietly();
        try {
            app(TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable) {
        }

        $activeBot = TelegramBot::query()
            ->where('status', 'active')
            ->whereNotNull('otp_api_key')
            ->first();

        try {
            $client = $activeBot ? $providerClient->forBot($activeBot) : $providerClient;
            $items = $client->getServices(timeout: 10);
            $synced = [];

            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? '');
                $slug = (string) ($item['slug'] ?? \Illuminate\Support\Str::slug($name));

                // Filter layanan Kopken dan WhatsApp OTP
                $isTarget = strcasecmp($name, 'Kopken') === 0 ||
                    strcasecmp($slug, 'kopken') === 0 ||
                    stripos($name, 'kopken') !== false ||
                    stripos($name, 'whatsapp') !== false ||
                    stripos($slug, 'whatsapp') !== false;

                if (! $isTarget) {
                    continue;
                }

                $providerPrice = (int) ($item['price'] ?? 0);
                $stock = (int) ($item['stock'] ?? $item['count'] ?? $item['available'] ?? 0);
                $sellPrice = $activeBot ? $activeBot->sellPriceFor($providerPrice) : $providerPrice;

                $existing = \App\Models\OtpService::where('provider_service_id', (int) $item['id'])->first();

                $svc = \App\Models\OtpService::updateOrCreate(
                    ['provider_service_id' => (int) $item['id']],
                    [
                        'name' => $name,
                        'slug' => $slug,
                        'provider_price' => $providerPrice,
                        'sell_price' => $sellPrice,
                        'duration_seconds' => (int) ($item['duration_seconds'] ?? 1200),
                        'stock' => $stock,
                        'is_active' => true,
                        'is_enabled' => $existing ? $existing->is_enabled : true, // Auto-enable jika baru
                    ]
                );

                $synced[] = [
                    'id' => $svc->id,
                    'name' => $svc->name,
                    'stock' => $stock,
                    'provider_price' => $providerPrice,
                    'sell_price' => $sellPrice,
                    'is_enabled' => (bool) $svc->is_enabled,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync layanan & stok berhasil disinkronkan.',
                'total_services' => count($synced),
                'services' => $synced,
                'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkronisasi stok: '.$e->getMessage(),
                'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
            ], 500);
        }
    }

    public function pollOtp(): JsonResponse
    {
        ignore_user_abort(true);
        @set_time_limit(90);

        $results = $this->pollPendingOtpQuietly();
        $stripped = 0;
        try {
            $stripped = app(\App\Services\TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('cron strip resend: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Poll OTP pending selesai.',
            'polled' => $results,
            'resend_buttons_stripped' => $stripped,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
    }

    /**
     * @return list<array{id: int, status: string}>
     */
    protected function pollPendingOtpQuietly(): array
    {
        try {
            $otp = app(OtpOrderService::class);
            $pending = OtpOrder::query()
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(25)
                ->get();

            $results = [];
            foreach ($pending as $order) {
                try {
                    $fresh = $otp->refreshOrder($order);
                    $results[] = [
                        'id' => (int) $order->id,
                        'status' => (string) $fresh->status,
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'id' => (int) $order->id,
                        'status' => 'error',
                    ];
                }
            }

            return $results;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('cron otp poll: '.$e->getMessage());

            return [];
        }
    }
}
