<?php

namespace App\Console\Commands;

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
        $allowedNames = ['KOPI KENANGAN', 'KOPKEN', 'KOPIKENANGAN', 'KOPKEN FILTER', 'KOPI KENANGAN FILTER', 'WHATSAPP', 'WA'];

        // 1. Ambil bot-bot aktif yang memiliki antrean auto order aktif (prioritas tertinggi)
        $autoOrderBots = TelegramBot::query()
            ->where('status', 'active')
            ->whereIn('id', OtpAutoOrder::query()
                ->where('status', 'active')
                ->whereColumn('ordered_quantity', '<', 'target_quantity')
                ->select('telegram_bot_id'))
            ->get();

        // 2. Ambil bot representatif untuk tiap provider
        $activeKopkenBot = TelegramBot::query()
            ->where('status', 'active')
            ->where('otp_provider', 'kopken')
            ->whereNotNull('otp_api_key')
            ->first();

        $activeWahubBot = TelegramBot::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('otp_provider', 'wahub')
                    ->orWhereNotNull('otp_wahub_api_key');
            })
            ->first();

        // Gabungkan semua bot yang perlu disinkronkan tanpa duplikasi
        $botsToSync = collect([$activeKopkenBot, $activeWahubBot])
            ->merge($autoOrderBots)
            ->filter()
            ->unique('id');

        $totalSynced = 0;
        $syncedProviders = [];

        foreach ($botsToSync as $bot) {
            $provider = $bot->activeOtpProvider();
            $syncedProviders[] = "{$bot->name} ({$provider})";

            try {
                $count = $otpOrderService->syncServices($allowedNames, $bot, $provider);
                $totalSynced += $count;
            } catch (\Throwable $e) {
                Log::warning("Console syncStock for bot #{$bot->id} ({$provider}) warning: ".$e->getMessage());
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

        $botList = ! empty($syncedProviders) ? implode(', ', $syncedProviders) : 'Default/Global';
        $this->info("Synced stock: {$totalSynced} services updated [{$botList}]");

        return self::SUCCESS;
    }
}
