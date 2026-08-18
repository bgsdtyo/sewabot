<?php

namespace App\Console\Commands;

use App\Services\OtpOrderWatcher;
use Illuminate\Console\Command;

class WatchOtpOrdersCommand extends Command
{
    protected $signature = 'otp:watch {ids : Comma-separated OTP order IDs}';

    protected $description = 'Poll provider and edit Telegram bubbles until OTP arrives';

    public function handle(OtpOrderWatcher $watcher): int
    {
        ignore_user_abort(true);
        @set_time_limit(180);

        $ids = collect(explode(',', (string) $this->argument('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            $this->error('No order IDs');

            return self::FAILURE;
        }

        $this->info('Watching: '.implode(',', $ids));

        if (count($ids) === 1) {
            $watcher->runWatchCycle($ids[0]);
        } else {
            $watcher->runWatchBatchCycle($ids);
        }

        return self::SUCCESS;
    }
}
