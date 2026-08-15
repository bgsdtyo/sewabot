<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OtpOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Pendapatan (7 hari)';

    protected static ?string $description = 'Sewa bot (paid) + omzet OTP completed';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $labels = [];
        $saas = [];
        $otp = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $labels[] = $day->translatedFormat('d M');

            $saas[] = (int) Order::query()
                ->where('status', 'paid')
                ->whereDate('paid_at', $day)
                ->sum('amount');

            $otp[] = (int) OtpOrder::query()
                ->where('status', 'completed')
                ->whereDate('completed_at', $day)
                ->sum('sell_price');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sewa Bot',
                    'data' => $saas,
                    'backgroundColor' => 'rgba(15, 23, 42, 0.85)',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Omzet OTP',
                    'data' => $otp,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.75)',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
