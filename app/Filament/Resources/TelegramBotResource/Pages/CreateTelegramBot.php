<?php

namespace App\Filament\Resources\TelegramBotResource\Pages;

use App\Filament\Resources\TelegramBotResource;
use App\Services\TelegramBotService;
use Filament\Resources\Pages\CreateRecord;

class CreateTelegramBot extends CreateRecord
{
    protected static string $resource = TelegramBotResource::class;

    protected function afterCreate(): void
    {
        $this->record->syncWebhookUrl();

        if ($this->record->status === 'active' && filled($this->record->token)) {
            app(TelegramBotService::class)->setWebhook($this->record->fresh());
        }
    }
}
