<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpStockAlert extends Model
{
    protected $fillable = [
        'telegram_bot_id',
        'bot_member_id',
        'otp_service_id',
    ];

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
}
