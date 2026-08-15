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
     * One watch window (~90s). Optionally chain another HTTP request if still pending.
     */
    public function runWatchCycle(int $orderId, bool $continueChain = true): void
    {
        ignore_user_abort(true);
        @set_time_limit(120);

        $deadline = time() + 90;

        while (time() < $deadline) {
            sleep(2);

            try {
                $order = OtpOrder::query()->find($orderId);

                if (! $order || in_array($order->status, ['cancelled', 'expired'], true)) {
                    return;
                }

                if ($order->provider_expire_at && $order->provider_expire_at->isPast()) {
                    return;
                }

                $previousOtp = $order->otp_code;
                $fresh = app(OtpOrderService::class)->refreshOrder($order);

                // If OTP arrived (either initial or resent)
                if (filled($fresh->otp_code) && $previousOtp === null) {
                    return;
                }

                if (in_array($fresh->status, ['cancelled', 'expired'], true)) {
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
