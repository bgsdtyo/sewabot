<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBot extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'name',
        'username',
        'token',
        'otp_api_key',
        'otp_markup_type',
        'otp_markup_percent',
        'deposit_whatsapp',
        'deposit_telegram',
        'deposit_note',
        'webhook_url',
        'status',
        'notes',
    ];

    protected $hidden = [
        'token',
        'otp_api_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function botMembers(): HasMany
    {
        return $this->hasMany(BotMember::class);
    }

    public function otpOrders(): HasMany
    {
        return $this->hasMany(OtpOrder::class);
    }

    public function telegramUrl(): ?string
    {
        if (! $this->username) {
            return null;
        }

        return 'https://t.me/'.ltrim($this->username, '@');
    }

    public function displayUsername(): string
    {
        if (! $this->username) {
            return '-';
        }

        return str_starts_with($this->username, '@') ? $this->username : '@'.$this->username;
    }

    public function buildWebhookUrl(): ?string
    {
        if (! $this->id) {
            return null;
        }

        $base = rtrim((string) config('services.telegram.webhook_base_url', 'https://bgsdtyo.net'), '/');
        $secret = (string) config('services.telegram.webhook_secret', 'sewabot-webhook-secret');

        return $base.'/telegram/webhook/'.$this->id.'?secret='.urlencode($secret);
    }

    public function syncWebhookUrl(): void
    {
        $url = $this->buildWebhookUrl();
        if ($url && $this->webhook_url !== $url) {
            $this->forceFill(['webhook_url' => $url])->saveQuietly();
        }
    }

    public function otpMarkupType(): string
    {
        return in_array($this->otp_markup_type, ['percent', 'flat'], true)
            ? $this->otp_markup_type
            : 'percent';
    }

    public function otpMarkupValue(): int
    {
        return max(0, (int) ($this->otp_markup_percent ?? 50));
    }

    /** @deprecated use otpMarkupValue() */
    public function otpMarkupPercent(): int
    {
        return $this->otpMarkupValue();
    }

    public function sellPriceFor(int $providerPrice): int
    {
        $value = $this->otpMarkupValue();

        if ($this->otpMarkupType() === 'flat') {
            return $providerPrice + $value;
        }

        return (int) ceil($providerPrice * (100 + $value) / 100);
    }

    public function formattedSellPriceFor(int $providerPrice): string
    {
        return 'Rp'.number_format($this->sellPriceFor($providerPrice), 0, ',', '.');
    }

    public function markupLabel(): string
    {
        if ($this->otpMarkupType() === 'flat') {
            return '+Rp'.number_format($this->otpMarkupValue(), 0, ',', '.');
        }

        return $this->otpMarkupValue().'%';
    }

    /**
     * Normalize WhatsApp contact to https://wa.me/...
     */
    public function depositWhatsappUrl(): ?string
    {
        $raw = trim((string) $this->deposit_whatsapp);
        if ($raw === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }

    /**
     * Normalize Telegram contact to https://t.me/...
     */
    public function depositTelegramUrl(): ?string
    {
        $raw = trim((string) $this->deposit_telegram);
        if ($raw === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        $username = ltrim($raw, '@');
        $username = preg_replace('#^(?:t\.me/|telegram\.me/)#i', '', $username) ?: '';

        if ($username === '') {
            return null;
        }

        return 'https://t.me/'.$username;
    }

    public function hasDepositContacts(): bool
    {
        return $this->depositWhatsappUrl() || $this->depositTelegramUrl();
    }
}
