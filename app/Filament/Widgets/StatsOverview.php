<?php

namespace App\Filament\Widgets;

use App\Models\BotMember;
use App\Models\Order;
use App\Models\OtpOrder;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeBots = TelegramBot::query()->where('status', 'active')->count();
        $totalBots = TelegramBot::query()->count();
        $assignedBots = TelegramBot::query()->where('status', 'assigned')->count();

        $activeSubs = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();

        $expiringSoon = Subscription::query()
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        $otpPending = OtpOrder::query()->where('status', 'pending')->count();
        $otpDoneToday = OtpOrder::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();
        $otpOmzetToday = (int) OtpOrder::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->sum('sell_price');
        $otpOmzetMonth = (int) OtpOrder::query()
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('sell_price');

        $topupToday = (int) WalletTransaction::query()
            ->where('type', 'topup')
            ->whereDate('created_at', today())
            ->sum('amount');

        $paidToday = (int) Order::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', today())
            ->sum('amount');

        $paidMonth = (int) Order::query()
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $members = BotMember::query()->count();
        $memberBalance = (int) BotMember::query()->sum('balance');
        $heldBalance = (int) BotMember::query()->sum('held_balance');
        $providerBalance = (int) TelegramBot::query()->whereNotNull('provider_balance')->sum('provider_balance');

        $rp = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.');

        return [
            Stat::make('Bot Aktif', $activeBots)
                ->description("Total {$totalBots} · assigned {$assignedBots}")
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('success'),

            Stat::make('Subscription Aktif', $activeSubs)
                ->description($expiringSoon > 0 ? "{$expiringSoon} habis ≤7 hari" : 'Tidak ada yang segera habis')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($expiringSoon > 0 ? 'warning' : 'success'),

            Stat::make('Order Pending', $pendingOrders)
                ->description('Sewa bot menunggu konfirmasi')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'gray'),

            Stat::make('Users', User::count())
                ->description("Member bot: {$members}")
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('OTP Pending', $otpPending)
                ->description('Menunggu kode masuk')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color($otpPending > 0 ? 'warning' : 'gray'),

            Stat::make('OTP Selesai Hari Ini', $otpDoneToday)
                ->description('Omzet '.$rp($otpOmzetToday))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Omzet OTP Bulan Ini', $rp($otpOmzetMonth))
                ->description('Charge OTP completed')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Deposit Hari Ini', $rp($topupToday))
                ->description('Topup saldo member')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info'),

            Stat::make('Paid Sewa Hari Ini', $rp($paidToday))
                ->description('Bulan ini '.$rp($paidMonth))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Saldo Member', $rp($memberBalance))
                ->description('Ditahan '.$rp($heldBalance))
                ->descriptionIcon('heroicon-m-wallet')
                ->color('primary'),

            Stat::make('Saldo Pusat API', $rp($providerBalance))
                ->description('Total cache saldo provider di bot')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('gray'),

            Stat::make('Member Bot', $members)
                ->description('User Telegram terdaftar')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
        ];
    }
}
