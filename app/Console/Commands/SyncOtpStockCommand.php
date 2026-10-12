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
            $countKopken = $otpOrderService->syncServices(['KOPI KENANGAN', 'KOPKEN', 'KOPIKENANGAN', 'WHATSAPP', 'WA'], $activeKopkenBot, 'kopken');
        } catch (\Throwable $e) {
            Log::warning('Console syncStock Kopken warning: '.$e->getMessage());
        }

        try {
            $countWahub = $otpOrderService->syncServices(['KOPI KENANGAN', 'KOPKEN', 'KOPIKENANGAN', 'WHATSAPP', 'WA'], $activeWahubBot, 'wahub');
        } catch (\Throwable $e) {
            Log::warning('Console syncStock WAHub warning: '.$e->getMessage());
        }

        $this->info("Synced stock: Kopken ({$countKopken}), WAHub ({$countWahub})");

        return self::SUCCESS;
    }
}
