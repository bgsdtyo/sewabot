<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'subscription_id',
        'telegram_bot_id',
        'invoice_number',
        'type',
        'amount',
        'duration_days',
        'periods',
        'payment_proof',
        'status',
        'admin_note',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'duration_days' => 'integer',
            'periods' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            if ($order->payment_proof && Storage::disk('public')->exists($order->payment_proof)) {
                Storage::disk('public')->delete($order->payment_proof);
            }
        });
    }

    public function formattedAmount(): string
    {
        return 'Rp'.number_format($this->amount, 0, ',', '.');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'MENUNGGU KONFIRMASI',
            'paid' => 'PAID',
            'rejected' => 'REJECTED',
            'expired' => 'EXPIRED',
            default => strtoupper($this->status),
        };
    }

    public function paymentProofUrl(): ?string
    {
        if (! $this->payment_proof) {
            return null;
        }

        return Storage::disk('public')->url($this->payment_proof);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $last = static::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
