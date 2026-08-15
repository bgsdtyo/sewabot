<?php

namespace App\Filament\Resources\TelegramBotResource\Pages;

use App\Filament\Resources\TelegramBotResource;
use App\Models\Subscription;
use App\Models\TelegramBot;
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
        $this->syncOwnerSubscription($this->record);

        if ($this->record->status === 'active' && filled($this->record->token)) {
            app(TelegramBotService::class)->setWebhook($this->record->fresh());
        }
    }

    /**
     * Dashboard user membaca bot lewat subscription.
     * Saat admin assign user_id di bot, pastikan subscription ikut ter-link.
     */
    protected function syncOwnerSubscription(TelegramBot $bot): void
    {
        if (! $bot->user_id || ! $bot->product_id) {
            return;
        }

        $bot->loadMissing('product');

        $subscription = Subscription::query()
            ->where('telegram_bot_id', $bot->id)
            ->first();

        $isActive = $bot->status === 'active';
        $productDays = (int) ($bot->product?->duration_days ?: 30);

        if ($subscription) {
            $updates = [
                'user_id' => $bot->user_id,
                'product_id' => $bot->product_id,
            ];

            if ($isActive && $subscription->status !== 'active') {
                $updates['status'] = 'active';
                $updates['started_at'] = $subscription->started_at ?? now();
                $updates['expires_at'] = $subscription->expires_at && $subscription->expires_at->isFuture()
                    ? $subscription->expires_at
                    : now()->addDays($productDays);
            }

            $subscription->update($updates);

            return;
        }

        Subscription::create([
            'user_id' => $bot->user_id,
            'product_id' => $bot->product_id,
            'telegram_bot_id' => $bot->id,
            'status' => $isActive ? 'active' : 'pending',
            'started_at' => $isActive ? now() : null,
            'expires_at' => $isActive ? now()->addDays($productDays) : null,
        ]);
    }
}
