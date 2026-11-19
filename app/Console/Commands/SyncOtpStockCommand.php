<?php

namespace App\Console\Commands;

use App\Models\OtpAutoOrder;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOtpStockCommand extends Command
{
    protected $signature = 'otp:sync-stock';

    protected $description = 'Sync latest stock and dispatch restock alerts to subscribed members';

    public function handle(OtpOrderService $otpOrderService): int
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $allowedNames = ['KOPI KENANGAN', 'KOPKEN', 'KOPIKENANGAN', 'WHATSAPP', 'WA'];

        $hasActiveAutoOrders = OtpAutoOrder::query()
            ->where('status', 'active')
            ->whereColumn('ordered_quantity', '<', 'target_quantity')
            ->exists();

        // Jika ada antrean auto order aktif, jalankan sniper loop selama ~50 detik (interval 3.5 detik)
        // agar bot langsung menangkap restock secara real-time dan tidak ketinggalan kuota.
        // Jika tidak ada antrean aktif, jalankan 1 kali saja untuk menghemat resource server.
        $deadline = $hasActiveAutoOrders ? time() + 50 : time() + 1;
        $iteration = 0;
        $totalSyncedOverall = 0;
        $syncedProviders = [];

        while (time() < $deadline) {
            $iteration++;
            if ($iteration > 1) {
                usleep(3500000); // jeda 3.5 detik antar putaran sniping
            }

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

            foreach ($botsToSync as $bot) {
                $providersToSync = $bot->availableOtpProviders();
                foreach ($providersToSync as $provider) {
                    $syncedProviders[] = "{$bot->name} ({$provider})";

                    try {
                        $count = $otpOrderService->syncServices($allowedNames, $bot, $provider);
                        $totalSynced += $count;
                    } catch (\Throwable $e) {
                        Log::warning("Console syncStock for bot #{$bot->id} ({$provider}) warning: ".$e->getMessage());
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
                                $orderedCount = app(\App\Services\OtpAutoOrderService::class)->processAutoOrdersForService($svc, $stockToUse);
                                if ($orderedCount > 0) {
                                    $this->info("🎯 SNIPER HIT! Berhasil memborong {$orderedCount} nomor untuk bot #{$bot->id} ({$provider})!");
                                    Log::info("🎯 SNIPER HIT! Berhasil memborong {$orderedCount} nomor untuk bot #{$bot->id} ({$provider})!");
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::debug("Sniper probe for bot #{$bot->id} ({$provider}): " . $e->getMessage());
                    }
                    // ───────────────────────────────────────────────────────────────
                }
            }

            // Jika tidak ada bot terdaftar sama sekali, coba fallback driver default
            if ($botsToSync->isEmpty()) {
                try {
                    $totalSynced += $otpOrderService->syncServices($allowedNames, null, 'wahub');
                } catch (\Throwable) {}
                try {
                    $totalSynced += $otpOrderService->syncServices($allowedNames, null, 'kopken');
                } catch (\Throwable) {}
            }

            $totalSyncedOverall += $totalSynced;

            // Jika semua auto order telah terpenuhi, hentikan loop lebih awal
            if ($hasActiveAutoOrders && ! OtpAutoOrder::query()->where('status', 'active')->whereColumn('ordered_quantity', '<', 'target_quantity')->exists()) {
                break;
            }
        }

        $botList = ! empty($syncedProviders) ? implode(', ', array_unique($syncedProviders)) : 'Default/Global';
        $this->info("Synced stock: {$totalSyncedOverall} updates [{$botList}] (iterations: {$iteration})");

        return self::SUCCESS;
    }
}
