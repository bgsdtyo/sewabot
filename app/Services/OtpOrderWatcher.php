<?php

namespace App\Services;

use App\Models\OtpOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class OtpOrderWatcher
{
    /**
     * Start watching immediately in a detached CLI process.
     * FPM afterResponse is not reliable on shared hosting (killed / deadlock).
     */
    public function start(OtpOrder $order): void
    {
        try {
            $this->spawn([(int) $order->id]);
        } catch (\Throwable $e) {
            Log::warning('OtpOrderWatcher start failed: '.$e->getMessage());
            $this->fallbackTerminating([(int) $order->id]);
        }
    }

    public function startBatch(array|\Illuminate\Support\Collection $orders): void
    {
        $orderIds = collect($orders)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($orderIds === []) {
            return;
        }

        try {
            $this->spawn($orderIds);
        } catch (\Throwable $e) {
            Log::warning('OtpOrderWatcher startBatch failed: '.$e->getMessage());
            $this->fallbackTerminating($orderIds);
        }
    }

    /**
     * Poll every pending order first (no Telegram), then edit bubbles.
     * Keep an id in the loop until the Telegram edit actually succeeds —
     * otherwise #3 gets dropped after a 429 and stays stale until Cek OTP.
     */
    public function runWatchBatchCycle(array $orderIds): void
    {
        ignore_user_abort(true);
        @set_time_limit(360);

        $deadline = time() + 300;
        $activeIds = array_values(array_unique($orderIds));
        $firstTick = true;

        Log::info('OtpOrderWatcher batch start', ['ids' => $activeIds, 'sapi' => PHP_SAPI]);

        while (time() < $deadline && $activeIds !== []) {
            if (! $firstTick) {
                sleep(2);
            }
            $firstTick = false;

            $toNotify = [];

            foreach ($activeIds as $k => $orderId) {
                try {
                    if ($this->bubbleDelivered((int) $orderId)) {
                        unset($activeIds[$k]);
                        continue;
                    }

                    $order = OtpOrder::query()->find($orderId);

                    if (! $order) {
                        unset($activeIds[$k]);
                        continue;
                    }

                    if ($order->status === 'cancelled' && app(OtpOrderService::class)->isIgnoringProviderCancel((int) $order->id)) {
                        continue;
                    }

                    if (in_array($order->status, ['completed', 'cancelled', 'expired'], true) || filled($order->otp_code)) {
                        $toNotify[] = $order;
                        continue;
                    }

                    $fresh = app(OtpOrderService::class)->refreshOrder($order, notify: false);

                    if (in_array($fresh->status, ['completed', 'cancelled', 'expired'], true) || filled($fresh->otp_code)) {
                        $toNotify[] = $fresh;
                    }
                } catch (\Throwable $e) {
                    Log::warning("OtpOrderWatcher batch tick failed on #{$orderId}: ".$e->getMessage());
                }
            }

            $activeIds = array_values($activeIds);

            foreach ($toNotify as $i => $done) {
                if ($i > 0) {
                    usleep(400000);
                }

                if (! $this->notifyWatchedOrder($done)) {
                    continue;
                }

                $this->markBubbleDelivered((int) $done->id);
                $activeIds = array_values(array_filter(
                    $activeIds,
                    fn ($id) => (int) $id !== (int) $done->id
                ));
            }
        }
    }

    public function runWatchCycle(int $orderId): void
    {
        ignore_user_abort(true);
        @set_time_limit(360);

        $deadline = time() + 300;
        $firstTick = true;

        Log::info('OtpOrderWatcher start', ['id' => $orderId, 'sapi' => PHP_SAPI]);

        while (time() < $deadline) {
            if (! $firstTick) {
                sleep(2);
            }
            $firstTick = false;

            try {
                if ($this->bubbleDelivered($orderId)) {
                    return;
                }

                $order = OtpOrder::query()->find($orderId);

                if (! $order) {
                    return;
                }

                if ($order->status === 'cancelled' && app(OtpOrderService::class)->isIgnoringProviderCancel((int) $order->id)) {
                    continue;
                }

                $target = $order;
                if (! (in_array($order->status, ['completed', 'cancelled', 'expired'], true) || filled($order->otp_code))) {
                    $target = app(OtpOrderService::class)->refreshOrder($order, notify: false);
                }

                if (in_array($target->status, ['cancelled', 'expired', 'completed'], true) || filled($target->otp_code)) {
                    if ($this->notifyWatchedOrder($target)) {
                        $this->markBubbleDelivered($orderId);

                        return;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OtpOrderWatcher tick failed: '.$e->getMessage());
            }
        }
    }

    protected function notifyWatchedOrder(OtpOrder $order): bool
    {
        try {
            $order = $order->fresh(['otpService', 'botMember', 'telegramBot']) ?? $order;
            $bot = $order->telegramBot;
            $member = $order->botMember;
            if (! $bot || ! $member) {
                Log::warning('OtpOrderWatcher notify skipped: missing bot/member', ['order' => $order->id]);

                return false;
            }

            if ($order->status === 'completed' || filled($order->otp_code)) {
                return app(TelegramBotService::class)->notifyOrderCompleted($bot, $member, $order);
            }

            if (in_array($order->status, ['cancelled', 'expired'], true)) {
                return app(TelegramBotService::class)->notifyOrderCancelled($bot, $member, $order, $order->status);
            }
        } catch (\Throwable $e) {
            Log::warning('OtpOrderWatcher notify failed: '.$e->getMessage(), ['order' => $order->id]);
        }

        return false;
    }

    protected function bubbleDelivered(int $orderId): bool
    {
        return Cache::has('otp_bubble_ok:'.$orderId);
    }

    protected function markBubbleDelivered(int $orderId): void
    {
        Cache::put('otp_bubble_ok:'.$orderId, 1, now()->addMinutes(20));
    }

    /**
     * Start detached CLI / background curl watcher first so FPM process termination
     * does not kill the watcher loop when later slots (#2, #3) are being inputted.
     */
    protected function spawn(array $orderIds): void
    {
        $idStr = implode(',', $orderIds);

        // 1. Coba spawn background CLI process via artisan
        if ($this->spawnArtisan($idStr)) {
            Log::info("OtpOrderWatcher spawned via Artisan for IDs: {$idStr}");

            return;
        }

        // 2. Coba spawn via async background curl
        if ($this->spawnCurl($orderIds)) {
            Log::info("OtpOrderWatcher spawned via Curl for IDs: {$idStr}");

            return;
        }

        // 3. Fallback in-process terminating
        $this->fallbackTerminating($orderIds);
    }

    protected function fallbackTerminating(array $orderIds): void
    {
        ignore_user_abort(true);
        @set_time_limit(360);

        app()->terminating(function () use ($orderIds) {
            ignore_user_abort(true);
            @set_time_limit(360);
            if (count($orderIds) === 1) {
                app(OtpOrderWatcher::class)->runWatchCycle($orderIds[0]);
            } else {
                app(OtpOrderWatcher::class)->runWatchBatchCycle($orderIds);
            }
        });
    }

    protected function spawnArtisan(string $ids): bool
    {
        $php = $this->phpCliPath();
        if ($php === null) {
            return false;
        }

        $artisan = base_path('artisan');
        $log = storage_path('logs/otp-watch.log');

        if (! is_file($artisan)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' otp:watch '.escapeshellarg($ids);

            return $this->runDetached($cmd);
        }

        $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
            .' otp:watch '.escapeshellarg($ids)
            .' >> '.escapeshellarg($log).' 2>&1 &';

        return $this->runDetached($cmd);
    }

    protected function spawnCurl(array $orderIds): bool
    {
        try {
            $url = URL::temporarySignedRoute(
                'otp.watch.batch',
                now()->addMinutes(25),
                ['ids' => implode(',', $orderIds)]
            );
        } catch (\Throwable $e) {
            Log::warning('OtpOrderWatcher signed URL failed: '.$e->getMessage());

            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        $inner = 'sleep 1; curl -s -m 170 '.escapeshellarg($url);
        $cmd = 'nohup sh -c '.escapeshellarg($inner).' >/dev/null 2>&1 &';

        return $this->runDetached($cmd);
    }

    protected function runDetached(string $cmd): bool
    {
        try {
            if (function_exists('exec') && ! $this->functionDisabled('exec')) {
                exec($cmd);

                return true;
            }

            if (function_exists('shell_exec') && ! $this->functionDisabled('shell_exec')) {
                shell_exec($cmd);

                return true;
            }

            if (function_exists('popen') && ! $this->functionDisabled('popen')) {
                $h = @popen($cmd, 'r');
                if (is_resource($h)) {
                    pclose($h);

                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OtpOrderWatcher runDetached: '.$e->getMessage());
        }

        return false;
    }

    protected function functionDisabled(string $name): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array($name, $disabled, true);
    }

    /**
     * Skip CLI spawn on open_basedir hosts — probing /opt/alt/php... fatals.
     */
    protected function phpCliPath(): ?string
    {
        $basedir = (string) ini_get('open_basedir');
        if ($basedir !== '') {
            return null;
        }

        $configured = (string) env('PHP_CLI_PATH', '');
        if ($configured !== '') {
            return $configured;
        }

        $candidates = [];

        $binDirPhp = rtrim((string) PHP_BINDIR, '/\\').DIRECTORY_SEPARATOR.'php';
        $candidates[] = $binDirPhp;
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = $binDirPhp.'.exe';
        }

        $current = (string) PHP_BINARY;
        if ($current !== '') {
            $asCli = preg_replace('/php-fpm(?:[0-9.]*)?$/i', 'php', $current);
            if (is_string($asCli) && $asCli !== $current) {
                $candidates[] = $asCli;
            }
            $dir = dirname($current);
            foreach (['php', 'php.exe', 'php8.3', 'php8.2', 'php83', 'php82'] as $name) {
                $candidates[] = $dir.DIRECTORY_SEPARATOR.$name;
            }
        }

        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';
        $candidates[] = 'php';

        foreach ($candidates as $path) {
            if ($path === 'php') {
                return $path;
            }
            if (! is_string($path) || $path === '' || str_contains(strtolower($path), 'fpm')) {
                continue;
            }
            try {
                if (@is_file($path) && @is_executable($path) && ! @is_dir($path)) {
                    return $path;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 'php';
    }
}
