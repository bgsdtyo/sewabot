<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OtpServiceResource\Pages;
use App\Models\OtpService;
use App\Services\OtpOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OtpServiceResource extends Resource
{
    protected static ?string $model = OtpService::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'OTP Provider';

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'OTP Service';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('provider_service_id')->label('Provider ID')->disabled(),
            Forms\Components\TextInput::make('provider_price')->prefix('Rp')->disabled()->label('Modal (dari API)'),
            Forms\Components\TextInput::make('stock')->disabled(),
            Forms\Components\TextInput::make('duration_seconds')->disabled(),
            Forms\Components\Toggle::make('is_enabled')->label('Aktif dijual ke bot'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('provider_service_id')->label('API ID'),
                Tables\Columns\TextColumn::make('provider_price')->money('IDR', locale: 'id')->label('Modal'),
                Tables\Columns\TextColumn::make('stock'),
                Tables\Columns\IconColumn::make('is_enabled')->boolean()->label('Enabled'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync KOPKEN')
                    ->action(function () {
                        try {
                            $n = app(OtpOrderService::class)->syncServices(['KOPKEN']);
                            Notification::make()->title("Synced {$n}")->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtpServices::route('/'),
            'edit' => Pages\EditOtpService::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
