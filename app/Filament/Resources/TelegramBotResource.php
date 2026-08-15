<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramBotResource\Pages;
use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramBotResource extends Resource
{
    protected static ?string $model = TelegramBot::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Telegram Bots';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')->relationship('product', 'name')->required(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->searchable()->nullable(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('username')
                ->prefix('@')
                ->helperText('Isi setelah bot di-build. Username baru muncul di dashboard user.'),
            Forms\Components\TextInput::make('token')
                ->password()
                ->revealable()
                ->helperText('Token dari BotFather. Kosongkan saat simpan jika tidak ingin mengubah token yang sudah tersimpan.')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
            Forms\Components\TextInput::make('otp_api_key')
                ->label('OTP Provider API Key')
                ->password()
                ->revealable()
                ->helperText('Per bot. Kosongkan saat simpan jika tidak ingin mengubah key yang sudah ada.')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),
            Forms\Components\Select::make('otp_markup_type')
                ->label('Tipe Markup')
                ->options([
                    'percent' => 'Persen (%)',
                    'flat' => 'Flat (Rp)',
                ])
                ->default('percent')
                ->required(),
            Forms\Components\TextInput::make('otp_markup_percent')
                ->label('Nilai Markup')
                ->numeric()
                ->default(50)
                ->minValue(0)
                ->helperText('Persen: modal + % · Flat: modal + Rp (contoh 1000).'),
            Forms\Components\Section::make('Kontak Deposit')
                ->description('Deposit saldo member masih manual. Tombol WA & Telegram muncul di bot.')
                ->schema([
                    Forms\Components\TextInput::make('deposit_whatsapp')
                        ->label('WhatsApp admin')
                        ->placeholder('62812xxxx atau https://wa.me/62812xxxx')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('deposit_telegram')
                        ->label('Telegram admin')
                        ->placeholder('@username atau https://t.me/username')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('deposit_bank_name')
                        ->label('Nama bank / e-wallet')
                        ->placeholder('BCA / DANA / GoPay')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('deposit_account_number')
                        ->label('No. rekening / no. HP')
                        ->placeholder('1234567890')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('deposit_account_name')
                        ->label('Atas nama')
                        ->placeholder('Nama pemilik rekening')
                        ->maxLength(100)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('admin_telegram_ids')
                        ->label('Admin Telegram ID')
                        ->placeholder('123456789, 987654321')
                        ->helperText('ID numerik admin (pisah koma). Untuk /admin /cek /adddeposit /rekap.')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsed(),
            Forms\Components\TextInput::make('webhook_url')
                ->label('Webhook URL')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function (?string $state, ?TelegramBot $record): ?string {
                    return $record?->buildWebhookUrl() ?? $state;
                })
                ->helperText('Otomatis dari domain '.config('services.telegram.webhook_base_url').' — tidak perlu diisi manual.'),
            Forms\Components\Select::make('status')->options([
                'available' => 'Available',
                'assigned' => 'Assigned',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ])->required(),
            Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('username'),
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('user.email')->label('Assigned To'),
                Tables\Columns\TextColumn::make('webhook_url')->label('Webhook')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'available' => 'info',
                    'assigned' => 'warning',
                    'active' => 'success',
                    'inactive' => 'danger',
                    default => 'gray',
                }),
            ])
            ->actions([
                Tables\Actions\Action::make('generateUsername')
                    ->label('Generate Username')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (TelegramBot $record) => blank($record->username) && filled($record->name))
                    ->action(function (TelegramBot $record) {
                        $username = \App\Support\BotUsernameGenerator::fromName($record->name);
                        $record->update(['username' => $username]);
                        Notification::make()->title('Username: @'.$username)->success()->send();
                    }),
                Tables\Actions\Action::make('setWebhook')
                    ->label('Set Webhook')
                    ->icon('heroicon-o-link')
                    ->visible(fn (TelegramBot $record) => filled($record->token))
                    ->action(function (TelegramBot $record) {
                        $ok = app(TelegramBotService::class)->setWebhook($record);
                        Notification::make()
                            ->title($ok ? 'Webhook di-set ke '.$record->fresh()->webhook_url : 'Gagal set webhook (cek token / domain HTTPS)')
                            ->{$ok ? 'success' : 'danger'}()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramBots::route('/'),
            'create' => Pages\CreateTelegramBot::route('/create'),
            'edit' => Pages\EditTelegramBot::route('/{record}/edit'),
        ];
    }
}
