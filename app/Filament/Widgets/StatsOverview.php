<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::count()),
            Stat::make('Pending Orders', Order::where('status', 'pending')->count())
                ->description('Menunggu konfirmasi')
                ->color('warning'),
            Stat::make('Active Subscriptions', Subscription::where('status', 'active')->where('expires_at', '>', now())->count())
                ->color('success'),
            Stat::make('Paid Today', 'Rp'.number_format(Order::where('status', 'paid')->whereDate('paid_at', today())->sum('amount'), 0, ',', '.')),
        ];
    }
}
