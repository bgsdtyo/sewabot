<?php

namespace App\Services;

use App\Models\OtpOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class OtpOrderWatcher
{
    /**
     * Start background polling right after Telegram webhook responds.
     * Does not rely on cron — critical on shared hosting.
     */
    public function start(OtpOrder $order): void
    {
        $orderId = (int) $order->id;

        dispatch(function () use ($orderId) {
            app(OtpOrderWatcher::class)->runWatchCycle($orderId, continueChain: true);
        })->afterResponse();
    }

    /**
     * Start background polling for multiple orders in a single worker process simultaneously.
     */
    public function startBatch(array|\Illuminate\Support\Collection $orders): void
    {
        $orderIds = collect($orders)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if (empty($orderIds)) {
            return;
        }

        dispatch(function () use ($orderIds) {
            app(OtpOrderWatcher::class)->runWatchBatchCycle($orderIds, continueChain: true);
        })->afterResponse();
    }

    /**
     * Watch multiple orders in a batch concurrently in each tick.
     */
    public function runWatchBatchCycle(array $orderIds, bool $continueChain = true): void
    {
        ignore_user_abort(true);
        @set_time_limit(150);

        $deadline = time() + 90;
        $activeIds = array_values(array_unique($orderIds));

        while (time() < $deadline && ! empty($activeIds)) {
            sleep(1);

            foreach ($activeIds as $k => $orderId) {
                try {
                    $order = OtpOrder::query()->find($orderId);

                    if (! $order || in_array($order->status, ['cancelled', 'expired'], true)) {
                        unset($activeIds[$k]);
                        continue;
                    }

                    if ($order->provider_expire_at && $order->provider_expire_at->isPast()) {
                        app(OtpOrderService::class)->refreshOrder($order);
                        unset($activeIds[$k]);
                        continue;
                    }

                    $fresh = app(OtpOrderService::class)->refreshOrder($order);

                    if (in_array($fresh->status, ['completed', 'cancelled', 'expired'], true)) {
                        unset($activeIds[$k]);
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::warning("OtpOrderWatcher batch tick failed on #{$orderId}: ".$e->getMessage());
                }
            }

            $activeIds = array_values($activeIds);
        }

        if (! $continueChain || empty($activeIds)) {
            return;
        }

        // Chain next window once for the remaining batch (controller expands to all pending).
        $remId = $activeIds[0] ?? null;
        if (! $remId) {
            return;
        }

        try {
            $url = URL::temporarySignedRoute(
                'otp.watch',
                now()->addMinutes(25),
                ['order' => $remId]
            );

            Http::timeout(1)
                ->withOptions(['http_errors' => false])
                ->get($url);
        } catch (\Throwable $e) {
            Log::debug('OtpOrderWatcher batch chain ping: '.$e->getMessage());
        }
    }

    /**
     * One watch window (~90s). Optionally chain another HTTP request if still pending.
     */
    public function runWatchCycle(int $orderId, bool $continueChain = true): void
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $deadline = time() + 90;
        $orderCreatedShown = false;
        $startTime = time();

        while (time() < $deadline) {
            sleep(1);

            try {
                $order = OtpOrder::query()->find($orderId);

                if (! $order || in_array($order->status, ['cancelled', 'expired'], true)) {
                    return;
                }

                if ($order->provider_expire_at && $order->provider_expire_at->isPast()) {
                    return;
                }

                $fresh = app(OtpOrderService::class)->refreshOrder($order);

                if (in_array($fresh->status, ['cancelled', 'expired', 'completed'], true)) {
                    return;
                }

                // After initial verification window (~5 seconds) and status is STILL healthy pending:
                if (! $orderCreatedShown && (time() - $startTime) >= 5) {
                    $orderCreatedShown = true;
                    $bot = $fresh->telegramBot;
                    $member = $fresh->botMember;
                    if ($bot && $member && $fresh->status === 'pending' && ! filled($fresh->otp_code)) {
                        app(TelegramBotService::class)->revealOrderCreated($bot, $member, $fresh);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OtpOrderWatcher tick failed: '.$e->getMessage());
            }
        }

        if (! $continueChain) {
            return;
        }

        $order = OtpOrder::query()->find($orderId);

        if (! $order || in_array($order->status, ['cancelled', 'expired'], true)) {
            return;
        }

        if (filled($order->otp_code)) {
            return;
        }

        // Chain next window via fire-and-forget HTTP (survives afterResponse limits).
        try {
            $url = URL::temporarySignedRoute(
                'otp.watch',
                now()->addMinutes(25),
                ['order' => $orderId]
            );

            Http::timeout(1)
                ->withOptions(['http_errors' => false])
                ->get($url);
        } catch (\Throwable $e) {
            // Timeout expected — request is fire-and-forget.
            Log::debug('OtpOrderWatcher chain ping: '.$e->getMessage());
        }
    }
}
