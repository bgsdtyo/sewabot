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
        return $query->where('is_active', true)
            ->where('is_enabled', true)
            ->whereRaw('UPPER(name) NOT LIKE ?', ['%SHOPEE%'])
            ->whereRaw('UPPER(name) NOT LIKE ?', ['%GOPAY%']);
    }

    public function scopeForProvider($query, ?string $provider = null)
    {
        $pv = strtolower(trim((string) ($provider ?: 'kopken')));

        return $query->where('provider', $pv);
    }

    public function scopeKopiKenangan($query, ?string $provider = null)
    {
        $q = $query->where(function ($sub) {
            $sub->whereIn('slug', ['kopi-kenangan', 'kopken', 'kopi_kenangan', 'kopikenangan', 'kopi', 'kopken-filter', 'kopi-kenangan-filter', 'filter-kopken'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPI KENANGAN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPIKENANGAN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN FILTER'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPI KENANGAN FILTER'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%KENANGAN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KENANGAN%']);
        })->whereRaw('UPPER(name) NOT LIKE ?', ['%SHOPEE%'])
          ->whereRaw('UPPER(name) NOT LIKE ?', ['%GOPAY%']);

        if ($provider) {
            $q->where('provider', strtolower(trim($provider)));
        }

        return $q;
    }

    public function scopeKopkenFilter($query, ?string $provider = null)
    {
        $q = $query->where(function ($sub) {
            $sub->whereIn('slug', ['kopken-filter', 'kopi-kenangan-filter', 'filter-kopken'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN FILTER'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPI KENANGAN FILTER'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%FILTER%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%KENANGAN%FILTER%'])
                ->orWhere(function ($sub2) {
                    $sub2->whereRaw('UPPER(name) LIKE ?', ['%FILTER%'])
                        ->where(function ($sub3) {
                            $sub3->whereRaw('UPPER(name) LIKE ?', ['%KOPI%'])
                                ->orWhereRaw('UPPER(name) LIKE ?', ['%KENANGAN%'])
                                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%']);
                        });
                });
        })->whereRaw('UPPER(name) NOT LIKE ?', ['%SHOPEE%'])
          ->whereRaw('UPPER(name) NOT LIKE ?', ['%GOPAY%']);

        if ($provider) {
            $q->where('provider', strtolower(trim($provider)));
        }

        return $q;
    }

    public function scopeKopken($query, ?string $provider = null)
    {
        $q = $query->where(function ($sub) {
            $sub->whereIn('slug', ['kopi-kenangan', 'kopken', 'kopi_kenangan', 'kopikenangan', 'kopi', 'kopken-filter', 'kopi-kenangan-filter', 'filter-kopken', 'whatsapp', 'wa'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPI KENANGAN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPIKENANGAN'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPKEN FILTER'])
                ->orWhereRaw('UPPER(name) = ?', ['KOPI KENANGAN FILTER'])
                ->orWhereRaw('UPPER(name) = ?', ['WHATSAPP'])
                ->orWhereRaw('UPPER(name) = ?', ['WA'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%KENANGAN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%KENANGAN%'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['%WHATSAPP%']);
        })->whereRaw('UPPER(name) NOT LIKE ?', ['%SHOPEE%'])
          ->whereRaw('UPPER(name) NOT LIKE ?', ['%GOPAY%']);

        if ($provider) {
            $q->where('provider', strtolower(trim($provider)));
        }

        return $q;
    }
}
