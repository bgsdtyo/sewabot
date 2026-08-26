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
            ->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->where('otp_provider', 'kopken')->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->where('otp_provider', 'wahub')->where(function ($q3) {
                        $q3->whereNotNull('otp_wahub_api_key')->where('otp_wahub_api_key', '!=', '')
                            ->orWhere(function ($q4) {
                                $q4->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '');
                            });
                    });
                });
            })
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
        \App\Services\OtpOrderService $otpOrderService
    ): JsonResponse {
        $this->pollPendingOtpQuietly();
        try {
            app(TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable) {
        }

        $activeKopkenBot = TelegramBot::query()
            ->where('status', 'active')
            ->where('otp_provider', 'kopken')
            ->whereNotNull('otp_api_key')
            ->first();

        $activeWahubBot = TelegramBot::query()
            ->where('status', 'active')
            ->where('otp_provider', 'wahub')
            ->where(function ($q) {
                $q->whereNotNull('otp_wahub_api_key')->where('otp_wahub_api_key', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '');
                    });
            })
            ->first();

        $countKopken = 0;
        $countWahub = 0;

        try {
            $countKopken = $otpOrderService->syncServices(['KOPKEN', 'WHATSAPP'], $activeKopkenBot, 'kopken');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cron syncStock Kopken warning: '.$e->getMessage());
        }

        try {
            $countWahub = $otpOrderService->syncServices(['KOPKEN', 'WHATSAPP', 'WA'], $activeWahubBot, 'wahub');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cron syncStock WAHub warning: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync layanan & stok KOPKEN berhasil disinkronkan.',
            'synced_kopken' => $countKopken,
            'synced_wahub' => $countWahub,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
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
            $deadline = time() + 25;
            $results = [];
            $firstLoop = true;

            while (time() < $deadline) {
                if (! $firstLoop) {
                    sleep(2);
                }
                $firstLoop = false;

                $pending = OtpOrder::query()
                    ->where(function ($q) {
                        $q->where('status', 'pending')
                            ->orWhere(function ($q2) {
                                $q2->where('status', 'completed')
                                    ->whereNull('otp_code')
                                    ->where('created_at', '>=', now()->subMinutes(20));
                            });
                    })
                    ->orderBy('id')
                    ->limit(25)
                    ->get();

                if ($pending->isEmpty()) {
                    break;
                }

                foreach ($pending as $order) {
                    try {
                        $fresh = $otp->refreshOrder($order, notify: true);
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
            }

            return $results;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('cron otp poll: '.$e->getMessage());

            return [];
        }
    }
}
