<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Orders';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('invoice_number')->disabled(),
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->disabled(),
            Forms\Components\Select::make('product_id')->relationship('product', 'name')->disabled(),
            Forms\Components\TextInput::make('amount')->prefix('Rp')->disabled(),
            Forms\Components\TextInput::make('duration_days')->label('Durasi (hari)')->disabled(),
            Forms\Components\TextInput::make('periods')->label('Periode')->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'rejected' => 'Rejected',
                    'expired' => 'Expired',
                ])
                ->disabled(),
            Forms\Components\FileUpload::make('payment_proof')
                ->disk('public')
                ->directory('payment-proofs')
                ->image()
                ->disabled()
                ->downloadable()
                ->openable(),
            Forms\Components\Textarea::make('admin_note')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable()->label('Invoice'),
                Tables\Columns\TextColumn::make('user.email')->searchable()->label('User'),
                Tables\Columns\TextColumn::make('product.name')->label('Product'),
                Tables\Columns\TextColumn::make('amount')->formatStateUsing(fn ($state) => 'Rp'.number_format($state, 0, ',', '.'))->label('Amount'),
                Tables\Columns\TextColumn::make('duration_days')->label('Hari')->sortable(),
                Tables\Columns\TextColumn::make('telegramBot.name')->label('Bot')->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\ImageColumn::make('payment_proof')->disk('public')->label('Proof')->square(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'rejected' => 'Rejected',
                    'expired' => 'Expired',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => in_array($record->status, ['pending', 'rejected'], true) && $record->payment_proof)
                    ->action(function (Order $record) {
                        app(OrderService::class)->approve($record);
                        Notification::make()->title('Order disetujui')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->form([
                        Forms\Components\Textarea::make('admin_note')->label('Catatan')->required(),
                    ])
                    ->visible(fn (Order $record) => $record->status === 'pending')
                    ->action(function (Order $record, array $data) {
                        app(OrderService::class)->reject($record, $data['admin_note']);
                        Notification::make()->title('Order ditolak')->danger()->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'product']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
