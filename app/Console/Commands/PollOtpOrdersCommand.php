<?php

namespace App\Console\Commands;

use App\Models\OtpOrder;
use App\Services\OtpOrderService;
use Illuminate\Console\Command;

class PollOtpOrdersCommand extends Command
{
    protected $signature = 'otp:poll';

    protected $description = 'Poll pending OTP orders from provider and settle wallet';

    public function handle(OtpOrderService $otp): int
    {
        $pending = OtpOrder::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($pending as $order) {
            try {
                $fresh = $otp->refreshOrder($order);
                $this->line("#{$order->id} => {$fresh->status}");
            } catch (\Throwable $e) {
                $this->error("#{$order->id} ".$e->getMessage());
            }
        }

        $this->info('Polled: '.$pending->count());

        try {
            $stripped = app(\App\Services\TelegramBotService::class)->stripExpiredResendButtons();
            $this->info('Resend buttons stripped: '.$stripped);
        } catch (\Throwable $e) {
            $this->warn('strip resend: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
