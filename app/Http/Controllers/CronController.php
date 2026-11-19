<?php

namespace App\Http\Controllers;

use App\Models\OtpAutoOrder;
use App\Models\OtpOrder;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Services\OtpAutoOrderService;
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
            ->with(['telegramBot.user'])
            ->get();

        $expiredSubCount = 0;
        $suspendedBotCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expiredSubCount++;

            if ($subscription->telegramBot && ! $subscription->telegramBot->hasActiveSubscription()) {
                $telegramBotService->deactivate($subscription->telegramBot);
                $suspendedBotCount++;
            }
        }

        // Also ensure any bot currently marked 'active' without ANY active subscription is suspended
        $activeBots = TelegramBot::query()
            ->where('status', 'active')
            ->with(['subscriptions', 'user'])
            ->get();

        foreach ($activeBots as $bot) {
            if (! $bot->hasActiveSubscription()) {
                $telegramBotService->deactivate($bot);
                $suspendedBotCount++;
            }
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
        OtpProviderManager $providerManager
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
        OtpOrderService $otpOrderService,
        OtpAutoOrderService $autoOrderService
    ): JsonResponse {
        try {
            app(TelegramBotService::class)->stripExpiredResendButtons();
        } catch (\Throwable) {
        }

        $allowedNames = ['KOPI KENANGAN', 'KOPKEN', 'KOPIKENANGAN', 'WHATSAPP', 'WA'];

        // 1. Ambil bot-bot aktif yang memiliki antrean auto order aktif (prioritas tertinggi)
        $autoOrderBots = TelegramBot::query()
            ->where('status', 'active')
            ->whereIn('id', OtpAutoOrder::query()
                ->where('status', 'active')
                ->whereColumn('ordered_quantity', '<', 'target_quantity')
                ->select('telegram_bot_id'))
            ->get();

        // 2. Ambil bot representatif untuk tiap provider (termasuk mode 'both')
        $activeKopkenBot = TelegramBot::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereIn('otp_provider', ['kopken', 'both'])
                    ->orWhereNotNull('otp_api_key');
            })
            ->whereNotNull('otp_api_key')
            ->first();

        $activeWahubBot = TelegramBot::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereIn('otp_provider', ['wahub', 'both'])
                    ->orWhereNotNull('otp_wahub_api_key');
            })
            ->whereNotNull('otp_wahub_api_key')
            ->first();

        // Gabungkan semua bot yang perlu disinkronkan tanpa duplikasi
        $botsToSync = collect([$activeKopkenBot, $activeWahubBot])
            ->merge($autoOrderBots)
            ->filter()
            ->unique('id');

        $totalSynced = 0;
        $totalSniped = 0;
        $syncedDetails = [];

        foreach ($botsToSync as $bot) {
            $providersToSync = $bot->availableOtpProviders();
            foreach ($providersToSync as $provider) {
                $syncedDetails[] = "{$bot->name} ({$provider})";

                try {
                    $count = $otpOrderService->syncServices($allowedNames, $bot, $provider);
                    $totalSynced += $count;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Cron syncStock for bot #{$bot->id} ({$provider}) warning: ".$e->getMessage());
                }

                // ── Active Sniper Probing ──────────────────────────────────────
                // Jangan hanya menunggu data GET /services yang mungkin terkena caching / reporting delay.
                // Jika bot memiliki antrean aktif yang masih butuh nomor, lakukan live probe order langsung!
                try {
                    $svc = $bot->activeOtpService($provider);
                    if ($svc) {
                        $hasWaitingOrders = OtpAutoOrder::query()
                            ->where('telegram_bot_id', $bot->id)
                            ->where(function ($q) use ($svc, $provider) {
                                $q->where('otp_service_id', $svc->id)
                                    ->orWhereHas('otpService', fn ($s) => $s->where('provider', $provider));
                            })
                            ->where('status', 'active')
                            ->whereColumn('ordered_quantity', '<', 'target_quantity')
                            ->exists();

                        if ($hasWaitingOrders) {
                            $stockToUse = (int) ($svc->stock ?? 0) > 0 ? (int) $svc->stock : 1;
                            $orderedCount = $autoOrderService->processAutoOrdersForService($svc, $stockToUse);
                            if ($orderedCount > 0) {
                                $totalSniped += $orderedCount;
                                \Illuminate\Support\Facades\Log::info("🎯 CRON SNIPER HIT! Berhasil memborong {$orderedCount} nomor untuk bot #{$bot->id} ({$provider})!");
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::debug("Cron sniper probe for bot #{$bot->id} ({$provider}): ".$e->getMessage());
                }
                // ───────────────────────────────────────────────────────────────
            }
        }

        // Fallback jika tidak ada bot terdaftar sama sekali
        if ($botsToSync->isEmpty()) {
            try {
                $totalSynced += $otpOrderService->syncServices($allowedNames, null, 'wahub');
            } catch (\Throwable) {}
            try {
                $totalSynced += $otpOrderService->syncServices($allowedNames, null, 'kopken');
            } catch (\Throwable) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync layanan & stok OTP provider selesai.',
            'synced_services' => $totalSynced,
            'sniped_numbers' => $totalSniped,
            'bots' => array_values(array_unique($syncedDetails)),
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

            try {
                app(\App\Services\TelegramBotService::class)->sendExpiringOrderReminders();
            } catch (\Throwable) {
            }

            return $results;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('cron otp poll: '.$e->getMessage());

            return [];
        }
    }
}
