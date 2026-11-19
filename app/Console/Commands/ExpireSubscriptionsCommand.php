<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\TelegramBot;
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
            ->with(['telegramBot.user'])
            ->get();

        $expiredSubCount = 0;
        $suspendedBotCount = 0;

        foreach ($due as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expiredSubCount++;

            if ($subscription->telegramBot && ! $subscription->telegramBot->hasActiveSubscription()) {
                $telegramBotService->deactivate($subscription->telegramBot);
                $suspendedBotCount++;
            }

            $this->info("Expired subscription #{$subscription->id}");
        }

        // Also suspend any bot currently marked 'active' without ANY active subscription
        $activeBots = TelegramBot::query()
            ->where('status', 'active')
            ->with(['subscriptions', 'user'])
            ->get();

        foreach ($activeBots as $bot) {
            if (! $bot->hasActiveSubscription()) {
                $telegramBotService->deactivate($bot);
                $suspendedBotCount++;
                $this->info("Suspended bot #{$bot->id} ({$bot->name})");
            }
        }

        // Pastikan seluruh bot yang tersuspend/kedaluwarsa tetap memiliki webhook aktif
        // agar selalu dapat membalas pesan fallback expired kepada pengguna di Telegram
        $suspendedBots = TelegramBot::query()
            ->where('status', 'suspended')
            ->whereNotNull('token')
            ->whereNull('webhook_url')
            ->get();

        foreach ($suspendedBots as $sBot) {
            if ($sBot->token) {
                $telegramBotService->setWebhook($sBot, force: true);
            }
        }

        $this->info("Done. Expired subscriptions: {$expiredSubCount}, Suspended bots: {$suspendedBotCount}");

        return self::SUCCESS;
    }
}
