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
        $this->pingWatch((int) $order->id);
    }

    /**
     * Ping one independent watcher HTTP request per order.
     * #3 must not wait behind Telegram edits for #1/#2 in the same PHP process.
     */
    public function startBatch(array|\Illuminate\Support\Collection $orders): void
    {
        $orderIds = collect($orders)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($orderIds === []) {
            return;
        }

        dispatch(function () use ($orderIds) {
            foreach ($orderIds as $orderId) {
                app(OtpOrderWatcher::class)->pingWatch($orderId, useAfterResponse: false);
                usleep(120000);
            }
        })->afterResponse();
    }

    /**
     * Fire-and-forget signed GET so a new PHP request polls this order alone.
     */
    public function pingWatch(int $orderId, bool $useAfterResponse = true): void
    {
        $ping = function () use ($orderId) {
            try {
                $url = URL::temporarySignedRoute(
                    'otp.watch',
                    now()->addMinutes(25),
                    ['order' => $orderId]
                );

                Http::timeout(1)
                    ->withOptions(['http_errors' => false, 'connect_timeout' => 1])
                    ->get($url);
            } catch (\Throwable $e) {
                Log::debug('OtpOrderWatcher ping: '.$e->getMessage());
            }
        };

        if ($useAfterResponse) {
            dispatch($ping)->afterResponse();

            return;
        }

        $ping();
    }

    /**
     * One watch window (~90s). Optionally chain another HTTP request if still pending.
     */
    public function runWatchCycle(int $orderId, bool $continueChain = true): void
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $deadline = time() + 90;
        $firstTick = true;

        while (time() < $deadline) {
            if (! $firstTick) {
                sleep(1);
            }
            $firstTick = false;

            try {
                $order = OtpOrder::query()->find($orderId);

                if (! $order || in_array($order->status, ['cancelled', 'expired', 'completed'], true)) {
                    return;
                }

                if ($order->provider_expire_at && $order->provider_expire_at->isPast()) {
                    app(OtpOrderService::class)->refreshOrder($order);

                    return;
                }

                $fresh = app(OtpOrderService::class)->refreshOrder($order);

                if (in_array($fresh->status, ['cancelled', 'expired', 'completed'], true) || filled($fresh->otp_code)) {
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('OtpOrderWatcher tick failed: '.$e->getMessage());
            }
        }

        if (! $continueChain) {
            return;
        }

        $order = OtpOrder::query()->find($orderId);

        if (! $order || in_array($order->status, ['cancelled', 'expired', 'completed'], true)) {
            return;
        }

        if (filled($order->otp_code)) {
            return;
        }

        $this->pingWatch($orderId, useAfterResponse: false);
    }
}
