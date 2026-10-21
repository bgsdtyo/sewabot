<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpAutoOrder extends Model
{
    protected $fillable = [
        'telegram_bot_id',
        'bot_member_id',
        'otp_service_id',
        'target_quantity',
        'ordered_quantity',
        'status',
        'last_order_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'target_quantity' => 'integer',
            'ordered_quantity' => 'integer',
            'last_order_at' => 'datetime',
        ];
    }

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    public function botMember(): BelongsTo
    {
        return $this->belongsTo(BotMember::class);
    }

    public function otpService(): BelongsTo
    {
        return $this->belongsTo(OtpService::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->target_quantity - (int) $this->ordered_quantity);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->remainingQuantity() > 0;
    }
}
