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
        'otp_provider',
        'otp_api_key',
        'otp_wahub_api_key',
        'provider_balance',
        'provider_balance_currency',
        'provider_balance_checked_at',
        'min_provider_balance_alert',
        'provider_balance_last_alerted_at',
        'otp_markup_type',
        'otp_markup_percent',
        'deposit_whatsapp',
        'deposit_telegram',
        'deposit_bank_name',
        'deposit_account_number',
        'deposit_account_name',
        'deposit_note',
        'admin_telegram_ids',
        'webhook_url',
        'status',
        'notes',
        'force_subscribe_enabled',
        'force_subscribe_channel',
        'force_subscribe_join_url',
    ];

    protected function casts(): array
    {
        return [
            'provider_balance' => 'integer',
            'provider_balance_checked_at' => 'datetime',
            'min_provider_balance_alert' => 'integer',
            'provider_balance_last_alerted_at' => 'datetime',
            'force_subscribe_enabled' => 'boolean',
        ];
    }

    protected $hidden = [
        'token',
        'otp_api_key',
        'otp_wahub_api_key',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'active';
    }

    public function hasValidToken(): bool
    {
        return filled($this->token);
    }

    public function maskedToken(): ?string
    {
        $token = trim((string) $this->token);
        if ($token === '') {
            return null;
        }

        $len = strlen($token);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        return substr($token, 0, 8) . str_repeat('•', 8) . substr($token, -4);
    }

    public function activeOtpProvider(): string
    {
        $provider = strtolower(trim((string) ($this->otp_provider ?: 'kopken')));

        return in_array($provider, ['kopken', 'wahub'], true) ? $provider : 'kopken';
    }

    public function otpProviderName(): string
    {
        return $this->activeOtpProvider() === 'wahub' ? 'WAHub (dehuyzotp.shop)' : 'EngineUnicorn (engineunicorn.cloud)';
    }

    public function activeOtpApiKey(): ?string
    {
        return $this->activeOtpProvider() === 'wahub'
            ? ($this->otp_wahub_api_key ?: $this->otp_api_key)
            : $this->otp_api_key;
    }

    public function hasOtpConfigured(): bool
    {
        return filled($this->activeOtpApiKey());
    }

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

    /**
     * @return list<string>
     */
    public function adminTelegramIdList(): array
    {
        $raw = trim((string) $this->admin_telegram_ids);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($id) => preg_replace('/\D+/', '', (string) $id) ?: '',
            $parts
        ))));
    }

    public function isTelegramAdmin(int|string $telegramId): bool
    {
        $id = preg_replace('/\D+/', '', (string) $telegramId) ?: '';

        return $id !== '' && in_array($id, $this->adminTelegramIdList(), true);
    }

    public function isForceSubscribeActive(): bool
    {
        return (bool) $this->force_subscribe_enabled && filled($this->force_subscribe_join_url);
    }

    /**
     * Return the join/invite URL for the force subscribe channel.
     */
    public function forceSubscribeJoinUrl(): ?string
    {
        return filled($this->force_subscribe_join_url) ? $this->force_subscribe_join_url : null;
    }

    /**
     * Try to derive a channel identifier (@username) from the join URL for getChatMember checks.
     * Returns null if URL is a private invite link (t.me/+...) or cannot be parsed.
     */
    public function forceSubscribeChannelId(): ?string
    {
        $url = trim((string) $this->force_subscribe_join_url);
        if ($url === '') {
            return null;
        }

        // Private invite links (t.me/+HASH) cannot be used as channel identifiers
        if (preg_match('#t\.me/\+#i', $url)) {
            return null;
        }

        // Public channel: https://t.me/channelname  or  t.me/channelname
        if (preg_match('#t\.me/([A-Za-z0-9_]{5,})#i', $url, $m)) {
            return '@' . $m[1];
        }

        return null;
    }

    public function formattedProviderBalance(): string
    {
        if ($this->provider_balance === null) {
            return '—';
        }

        return 'Rp'.number_format((int) $this->provider_balance, 0, ',', '.');
    }

    public function formattedMinProviderBalanceAlert(): string
    {
        if (! $this->min_provider_balance_alert) {
            return 'Nonaktif';
        }

        return 'Rp'.number_format((int) $this->min_provider_balance_alert, 0, ',', '.');
    }

    public function isProviderBalanceLow(): bool
    {
        if (! $this->min_provider_balance_alert || $this->min_provider_balance_alert <= 0) {
            return false;
        }

        if ($this->provider_balance === null) {
            return false;
        }

        return (int) $this->provider_balance <= (int) $this->min_provider_balance_alert;
    }
}
