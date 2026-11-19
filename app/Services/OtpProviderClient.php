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
    public function forBot(TelegramBot $bot): static
    {
        $key = $bot->otp_api_key ?: $bot->otp_wahub_api_key;
        if (! filled($key)) {
            throw new \RuntimeException('API Key belum diisi di Konfigurasi Bot.');
        }

        return $this->withApiKey($key);
    }
}
