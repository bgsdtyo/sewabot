<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpOrder extends Model
{
    protected $fillable = [
        'batch_id',
        'telegram_bot_id',
        'bot_member_id',
        'otp_service_id',
        'provider',
        'provider_order_id',
        'provider_token',
        'idempotency_key',
        'phone_number',
        'otp_code',
        'full_text',
        'provider_price',
        'sell_price',
        'status',
        'wallet_status',
        'provider_expire_at',
        'completed_at',
        'cancelled_at',
        'raw_payload',
        'telegram_message_id',
    ];

    protected function casts(): array
    {
        return [
            'provider_price' => 'integer',
            'sell_price' => 'integer',
            'provider_expire_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'raw_payload' => 'array',
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

    public function isPartOfBatch(): bool
    {
        return filled($this->batch_id);
    }

    public function batchSlotNumber(): int
    {
        if (! $this->isPartOfBatch()) {
            return 1;
        }

        return (int) static::query()
            ->where('batch_id', $this->batch_id)
            ->where('id', '<=', $this->id)
            ->count();
    }

    public function getBatchOrders()
    {
        if (! $this->isPartOfBatch()) {
            return collect([$this]);
        }

        return static::query()
            ->where('batch_id', $this->batch_id)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function otpWindowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->provider_expire_at) {
            return $this->provider_expire_at;
        }

        $seconds = (int) ($this->otpService?->duration_seconds ?: 1200);
        if ($seconds <= 0 || ! $this->created_at) {
            return null;
        }

        return $this->created_at->copy()->addSeconds($seconds);
    }

    public function canResendOtp(): bool
    {
        if (! in_array($this->status, ['pending', 'completed'], true)) {
            return false;
        }

        $expiresAt = $this->otpWindowExpiresAt();

        return $expiresAt !== null && $expiresAt->isFuture();
    }
}
