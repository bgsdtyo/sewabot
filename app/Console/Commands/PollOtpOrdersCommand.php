<?php

namespace App\Console\Commands;

use App\Models\OtpOrder;
use App\Services\OtpOrderService;
use Illuminate\Console\Command;

class PollOtpOrdersCommand extends Command
{
    protected $signature = 'otp:poll {--once : Run once without continuous loop}';

    protected $description = 'Poll pending OTP orders continuously from provider and settle wallet';

    public function handle(OtpOrderService $otp): int
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $isOnce = (bool) $this->option('once');
        $deadline = $isOnce ? time() + 1 : time() + 55;
        $firstLoop = true;
        $totalPolled = 0;

        while (time() < $deadline) {
            if (! $firstLoop) {
                usleep(800000); // 800ms ultra-fast polling
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
                ->limit(50)
                ->get();

            if ($pending->isEmpty()) {
                if ($isOnce) {
                    break;
                }
                sleep(3);
                continue;
            }

            foreach ($pending as $order) {
                try {
                    $fresh = $otp->refreshOrder($order, notify: true);
                    $totalPolled++;
                    $this->line("#{$order->id} => {$fresh->status} (OTP: ".($fresh->otp_code ?: '-').')');
                } catch (\Throwable $e) {
                    $this->error("#{$order->id} ".$e->getMessage());
                }
            }

            if ($isOnce) {
                break;
            }
        }

        $this->info('Polled cycles finished. Total checks: '.$totalPolled);

        try {
            $stripped = app(\App\Services\TelegramBotService::class)->stripExpiredResendButtons();
            $this->info('Resend buttons stripped: '.$stripped);
        } catch (\Throwable $e) {
            $this->warn('strip resend: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
