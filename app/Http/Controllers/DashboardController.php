<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $subscription = $user->subscriptions()
            ->with(['product', 'telegramBot'])
            ->latest('id')
            ->first();

        $notifications = $user->unreadNotifications()->latest()->take(5)->get();

        $bot = $subscription?->telegramBot;
        $members = null;
        $otpOrders = null;

        if ($bot) {
            $members = $bot->botMembers()->latest()->paginate(10, ['*'], 'members');
            $otpOrders = OtpOrder::query()
                ->where('telegram_bot_id', $bot->id)
                ->with(['botMember', 'otpService'])
                ->latest()
                ->paginate(10, ['*'], 'otp');
        }

        return view('dashboard.index', compact('user', 'subscription', 'notifications', 'bot', 'members', 'otpOrders'));
    }
}
