<?php

namespace App\Console\Commands;

use App\Models\OtpOrder;
use App\Services\OtpOrderService;
use Illuminate\Console\Command;

class PollOtpOrdersCommand extends Command
{
    protected $signature = 'otp:poll {--once : Run once without continuous loop} {--order= : Specific internal order ID or provider order ID to refresh}';

    protected $description = 'Poll pending OTP orders continuously from provider and settle wallet';

    public function handle(OtpOrderService $otp): int
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $specificOrder = $this->option('order');
        if ($specificOrder) {
            $order = OtpOrder::query()
                ->where('provider_order_id', $specificOrder)
                ->orWhere('id', (int) $specificOrder)
                ->first();

            if (! $order) {
                $this->error("Order '{$specificOrder}' tidak ditemukan.");

                return self::FAILURE;
            }

            $this->info("Refreshing Order #{$order->id} (Provider ID: {$order->provider_order_id})...");
            try {
                $fresh = $otp->refreshOrder($order, notify: true);
                $this->info("Order #{$order->id} => Status: {$fresh->status}, Wallet: {$fresh->wallet_status}, OTP: " . ($fresh->otp_code ?: '-'));
            } catch (\Throwable $e) {
                $this->error("Gagal refresh: " . $e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

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
                                ->where('created_at', '>=', now()->subMinutes(60));
                        })
                        ->orWhere(function ($q3) {
                            $q3->where('status', 'completed')
                                ->whereNotNull('otp_code')
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
                    if ($order->status === 'completed' && filled($order->otp_code)) {
                        if (! app(\App\Services\OtpOrderWatcher::class)->bubbleDelivered((int) $order->id)) {
                            $bot = $order->telegramBot;
                            $member = $order->botMember;
                            if ($bot && $member) {
                                app(\App\Services\TelegramBotService::class)->notifyOrderCompleted($bot, $member, $order);
                                $totalPolled++;
                                $this->line("#{$order->id} => bubble updated (OTP: {$order->otp_code})");
                            }
                        }
                        continue;
                    }

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
