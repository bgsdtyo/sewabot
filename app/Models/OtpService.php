<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OtpService extends Model
{
    protected $fillable = [
        'provider',
        'provider_service_id',
        'name',
        'slug',
        'provider_price',
        'sell_price',
        'duration_seconds',
        'stock',
        'is_active',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'provider_service_id' => 'integer',
            'provider_price' => 'integer',
            'sell_price' => 'integer',
            'duration_seconds' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OtpService $service) {
            if (empty($service->provider)) {
                $service->provider = 'kopken';
            }
            if (empty($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
        });
    }

    public function otpOrders(): HasMany
    {
        return $this->hasMany(OtpOrder::class);
    }

    public function formattedSellPrice(): string
    {
        return 'Rp'.number_format($this->sell_price, 0, ',', '.');
    }

    public function formattedProviderPrice(): string
    {
        return 'Rp'.number_format($this->provider_price, 0, ',', '.');
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->where('is_enabled', true);
    }

    public function scopeForProvider($query, ?string $provider = null)
    {
        $pv = strtolower(trim((string) ($provider ?: 'kopken')));

        return $query->where('provider', $pv);
    }

    public function scopeKopken($query, ?string $provider = null)
    {
        $q = $query->where(function ($sub) {
            $sub->where('slug', 'kopken')
                ->orWhere('slug', 'whatsapp')
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN'])
                ->orWhereRaw('UPPER(name) = ?', ['WHATSAPP'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%WHATSAPP%']);
        });

        if ($provider) {
            $q->where('provider', strtolower(trim($provider)));
        }

        return $q;
    }
}
