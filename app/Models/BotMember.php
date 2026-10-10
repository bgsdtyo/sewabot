<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotMember extends Model
{
    protected $fillable = [
        'telegram_bot_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_name',
        'balance',
        'held_balance',
        'is_active',
        'can_order',
        'can_receive_broadcast',
        'ban_reason',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'held_balance' => 'integer',
            'is_active' => 'boolean',
            'can_order' => 'boolean',
            'can_receive_broadcast' => 'boolean',
        ];
    }

    public function isBanned(): bool
    {
        return ! $this->is_active;
    }

    public function canOrder(): bool
    {
        return $this->is_active && $this->can_order;
    }

    public function canReceiveBroadcast(): bool
    {
        return $this->is_active && $this->can_receive_broadcast;
    }

    public function restrictionStatusLabel(): string
    {
        if (! $this->is_active) {
            return 'Banned Total';
        }

        if (! $this->can_order && ! $this->can_receive_broadcast) {
            return 'Order & Notif Diblokir';
        }

        if (! $this->can_order) {
            return 'Order Diblokir';
        }

        if (! $this->can_receive_broadcast) {
            return 'Notif Dimute';
        }

        return 'Aktif Normal';
    }

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function otpOrders(): HasMany
    {
        return $this->hasMany(OtpOrder::class);
    }

    public function availableBalance(): int
    {
        return max(0, (int) $this->balance - (int) $this->held_balance);
    }

    public function formattedBalance(): string
    {
        return 'Rp'.number_format($this->balance, 0, ',', '.');
    }

    public function formattedAvailable(): string
    {
        return 'Rp'.number_format($this->availableBalance(), 0, ',', '.');
    }

    public function displayName(): string
    {
        if (filled($this->telegram_username)) {
            return '@'.ltrim((string) $this->telegram_username, '@');
        }

        if (filled($this->telegram_name)) {
            return (string) $this->telegram_name;
        }

        return $this->telegram_chat_id ? 'ID: '.$this->telegram_chat_id : 'Member';
    }
}
