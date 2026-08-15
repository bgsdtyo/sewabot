<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->visible(fn (Order $record) => in_array($record->status, ['pending', 'rejected'], true) && $record->payment_proof)
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    app(OrderService::class)->approve($record);
                    Notification::make()->title('Order disetujui')->success()->send();
                    $this->refreshFormData(['status', 'paid_at', 'admin_note']);
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (Order $record) => $record->status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('admin_note')->required(),
                ])
                ->action(function (Order $record, array $data) {
                    app(OrderService::class)->reject($record, $data['admin_note']);
                    Notification::make()->title('Order ditolak')->danger()->send();
                    $this->refreshFormData(['status', 'admin_note']);
                }),
            Actions\EditAction::make(),
        ];
    }
}
