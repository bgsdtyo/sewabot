<?php

namespace App\Filament\Resources\OtpServiceResource\Pages;

use App\Filament\Resources\OtpServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOtpService extends EditRecord
{
    protected static string $resource = OtpServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
