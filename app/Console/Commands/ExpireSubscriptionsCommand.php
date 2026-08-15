<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire due subscriptions and deactivate telegram bots';

    public function handle(TelegramBotService $telegramBotService): int
    {
        $due = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('telegramBot')
            ->get();

        foreach ($due as $subscription) {
            $telegramBotService->expireSubscription($subscription);
            $this->info("Expired subscription #{$subscription->id}");
        }

        $this->info('Done. Expired: '.$due->count());

        return self::SUCCESS;
    }
}
