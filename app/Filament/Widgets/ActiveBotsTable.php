<?php

namespace App\Filament\Widgets;

use App\Models\TelegramBot;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveBotsTable extends BaseWidget
{
    protected static ?string $heading = 'Performa Bot Aktif';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TelegramBot::query()
                    ->where('status', 'active')
                    ->with(['user'])
                    ->withCount([
                        'botMembers as members_count',
                        'otpOrders as otp_pending_count' => fn ($q) => $q->where('status', 'pending'),
                        'otpOrders as otp_today_count' => fn ($q) => $q->whereDate('created_at', today()),
                        'otpOrders as otp_done_today_count' => fn ($q) => $q
                            ->where('status', 'completed')
                            ->whereDate('completed_at', today()),
                    ])
                    ->withSum([
                        'otpOrders as otp_omzet_today' => fn ($q) => $q
                            ->where('status', 'completed')
                            ->whereDate('completed_at', today()),
                    ], 'sell_price')
                    ->orderByDesc('otp_done_today_count')
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Bot')
                    ->searchable()
                    ->description(fn (TelegramBot $record) => $record->displayUsername()),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Owner')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Member')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('otp_pending_count')
                    ->label('OTP Pending')
                    ->alignCenter()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('otp_today_count')
                    ->label('Order Hari Ini')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('otp_done_today_count')
                    ->label('Selesai Hari Ini')
                    ->alignCenter()
                    ->color('success'),
                Tables\Columns\TextColumn::make('otp_omzet_today')
                    ->label('Omzet Hari Ini')
                    ->formatStateUsing(fn ($state) => 'Rp'.number_format((int) $state, 0, ',', '.'))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('provider_balance')
                    ->label('Saldo API')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : 'Rp'.number_format((int) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider_balance_checked_at')
                    ->label('Cek Saldo')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada bot aktif')
            ->emptyStateDescription('Bot aktif muncul di sini setelah order disetujui / diaktifkan.');
    }
}
