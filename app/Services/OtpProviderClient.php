<?php

namespace App\Services;

use App\Contracts\OtpProviderInterface;
use App\Models\TelegramBot;
use App\Services\OtpProviders\KopkenProvider;

/**
 * Legacy proxy/wrapper for OtpProviderClient, delegating to OtpProviderManager / KopkenProvider.
 */
class OtpProviderClient extends KopkenProvider
{
    public function forBot(TelegramBot $bot): OtpProviderInterface
    {
        return app(OtpProviderManager::class)->forBot($bot);
    }
}
