<?php

namespace App\Filament\Resources\TelegramBotResource\Pages;

use App\Filament\Resources\TelegramBotResource;
use App\Services\TelegramBotService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTelegramBot extends EditRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('setWebhook')
                ->label('Set Webhook ke Telegram')
                ->icon('heroicon-o-link')
                ->color('success')
                ->visible(fn () => filled($this->record->token))
                ->action(function () {
                    $this->record->syncWebhookUrl();
                    $ok = app(TelegramBotService::class)->setWebhook($this->record->fresh());
                    Notification::make()
                        ->title($ok ? 'Webhook berhasil di-set' : 'Gagal set webhook')
                        ->body($ok ? $this->record->fresh()->webhook_url : 'Pastikan token valid dan domain HTTPS publik.')
                        ->{$ok ? 'success' : 'danger'}()
                        ->send();
                    $this->refreshFormData(['webhook_url']);
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->syncWebhookUrl();

        if ($this->record->status === 'active' && filled($this->record->token)) {
            app(TelegramBotService::class)->setWebhook($this->record->fresh());
        }
    }
}
