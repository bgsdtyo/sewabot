<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_activation',
        'price_renewal',
        'duration_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_activation' => 'integer',
            'price_renewal' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function telegramBots(): HasMany
    {
        return $this->hasMany(TelegramBot::class);
    }

    public function formattedActivationPrice(): string
    {
        return 'Rp'.number_format($this->price_activation, 0, ',', '.');
    }

    public function formattedRenewalPrice(): string
    {
        return 'Rp'.number_format($this->price_renewal, 0, ',', '.');
    }

    /**
     * New subscription: first 30 days = activation, each extra period + renewal price.
     */
    public function priceForPeriods(int $periods, bool $renewal = false): int
    {
        $periods = max(1, $periods);

        if ($renewal) {
            return $this->price_renewal * $periods;
        }

        return $this->price_activation + (($periods - 1) * $this->price_renewal);
    }

    public function daysForPeriods(int $periods): int
    {
        return max(1, $periods) * ($this->duration_days ?: 30);
    }

    public function formatRp(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
