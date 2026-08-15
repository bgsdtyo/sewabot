<?php

namespace App\Filament\Widgets;

use App\Models\OtpOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OtpActivityChart extends ChartWidget
{
    protected static ?string $heading = 'Aktivitas OTP (7 hari)';

    protected static ?string $description = 'Order completed vs cancelled/expired';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $labels = [];
        $completed = [];
        $failed = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $labels[] = $day->translatedFormat('d M');

            $completed[] = OtpOrder::query()
                ->where('status', 'completed')
                ->whereDate('completed_at', $day)
                ->count();

            $failed[] = OtpOrder::query()
                ->whereIn('status', ['cancelled', 'expired'])
                ->whereDate('updated_at', $day)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Selesai',
                    'data' => $completed,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.25)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Batal / Expired',
                    'data' => $failed,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
