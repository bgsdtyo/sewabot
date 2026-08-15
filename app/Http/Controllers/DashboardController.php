<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        // Prefer active subscription, else latest subscription.
        $subscription = $user->subscriptions()
            ->with(['product', 'telegramBot'])
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        if (! $subscription) {
            $subscription = $user->subscriptions()
                ->with(['product', 'telegramBot'])
                ->latest('id')
                ->first();
        }

        // Bot: from subscription first, otherwise any bot assigned to this user.
        $bot = $subscription?->telegramBot
            ?? $user->telegramBots()->latest('id')->first();

        // If bot exists but subscription missing/mismatched, try subscription by bot.
        if ($bot && (! $subscription || (int) $subscription->telegram_bot_id !== (int) $bot->id)) {
            $botSubscription = $bot->subscriptions()
                ->with('product')
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();

            if ($botSubscription) {
                $subscription = $botSubscription;
            }
        }

        $notifications = $user->unreadNotifications()->latest()->take(5)->get();

        $members = null;
        $otpOrders = null;

        if ($bot) {
            $members = $bot->botMembers()
                ->latest('id')
                ->paginate(5, ['*'], 'members_page');

            $otpOrders = OtpOrder::query()
                ->where('telegram_bot_id', $bot->id)
                ->with(['botMember', 'otpService'])
                ->latest('id')
                ->paginate(5, ['*'], 'otp_page');
        }

        return view('dashboard.index', compact('user', 'subscription', 'notifications', 'bot', 'members', 'otpOrders'));
    }
}
