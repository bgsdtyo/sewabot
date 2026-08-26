<?php

namespace App\Services;

use App\Contracts\OtpProviderInterface;
use App\Models\OtpOrder;
use App\Models\Setting;
use App\Models\TelegramBot;
use App\Services\OtpProviders\KopkenProvider;
use App\Services\OtpProviders\WahubProvider;
use InvalidArgumentException;

class OtpProviderManager
{
    public const PROVIDER_KOPKEN = 'kopken';
    public const PROVIDER_WAHUB = 'wahub';

    public function __construct(
        protected KopkenProvider $kopkenProvider,
        protected WahubProvider $wahubProvider
    ) {}

    /**
     * Get a provider instance by name ('kopken' or 'wahub').
     */
    public function driver(?string $name = null): OtpProviderInterface
    {
        $name = strtolower(trim((string) ($name ?: $this->defaultProvider())));

        return match ($name) {
            self::PROVIDER_KOPKEN, 'default' => $this->kopkenProvider,
            self::PROVIDER_WAHUB, 'dehuyz' => $this->wahubProvider,
            default => throw new InvalidArgumentException("Provider OTP tidak dikenali: {$name}"),
        };
    }

    /**
     * Get configured provider driver for a specific TelegramBot.
     */
    public function forBot(TelegramBot $bot): OtpProviderInterface
    {
        $providerName = $bot->activeOtpProvider();
        $driver = $this->driver($providerName);

        return $driver->forBot($bot);
    }

    /**
     * Get configured provider driver for an existing OtpOrder.
     */
    public function forOrder(OtpOrder $order): OtpProviderInterface
    {
        $providerName = $order->provider ?: self::PROVIDER_KOPKEN;
        $driver = $this->driver($providerName);

        $bot = $order->telegramBot;
        if ($bot) {
            return $driver->forBot($bot);
        }

        return $driver;
    }

    /**
     * Get default active provider configured globally.
     */
    public function defaultProvider(): string
    {
        return (string) Setting::get('active_otp_provider', self::PROVIDER_KOPKEN);
    }

    public function kopken(?string $apiKey = null): KopkenProvider
    {
        return $apiKey ? $this->kopkenProvider->withApiKey($apiKey) : $this->kopkenProvider;
    }

    public function wahub(?string $apiKey = null): WahubProvider
    {
        return $apiKey ? $this->wahubProvider->withApiKey($apiKey) : $this->wahubProvider;
    }
}
