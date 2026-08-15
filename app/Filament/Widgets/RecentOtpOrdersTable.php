<?php

namespace App\Filament\Widgets;

use App\Models\OtpOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOtpOrdersTable extends BaseWidget
{
    protected static ?string $heading = 'OTP Terbaru';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OtpOrder::query()
                    ->with(['telegramBot', 'botMember', 'otpService'])
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('telegramBot.name')
                    ->label('Bot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('botMember')
                    ->label('Member')
                    ->state(fn (OtpOrder $record): string => $record->botMember?->displayName() ?? '-')
                    ->searchable(query: function ($query, string $search) {
                        $clean = ltrim($search, '@');
                        $query->whereHas('botMember', function ($q) use ($search, $clean) {
                            $q->where('telegram_username', 'like', "%{$clean}%")
                                ->orWhere('telegram_name', 'like', "%{$search}%")
                                ->orWhere('telegram_chat_id', 'like', "%{$search}%");
                        });
                    })
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('otpService.name')
                    ->label('Layanan'),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Nomor')
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('otp_code')
                    ->label('OTP')
                    ->placeholder('-')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('sell_price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => 'Rp'.number_format((int) $state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->defaultSort('id', 'desc');
    }
}
