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
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'held_balance' => 'integer',
            'is_active' => 'boolean',
        ];
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
        if ($this->telegram_username) {
            return '@'.ltrim($this->telegram_username, '@');
        }

        return $this->telegram_name ?: 'Chat '.$this->telegram_chat_id;
    }
}
