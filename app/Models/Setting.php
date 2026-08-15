<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('app_settings', 60, function () {
            return static::query()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings');
    }

    public static function payment(): array
    {
        return [
            'qris_image' => static::get('qris_image'),
            'merchant_name' => static::get('merchant_name', 'NAMA BISNIS'),
            'payment_instruction' => static::get('payment_instruction', 'Scan QRIS, transfer sesuai nominal, lalu upload bukti pembayaran.'),
        ];
    }

    public static function otpProvider(): array
    {
        return [
            'api_base_url' => rtrim((string) static::get('otp_api_base_url', env('OTP_API_BASE_URL', '')), '/'),
            'api_key' => (string) static::get('otp_api_key', env('OTP_API_KEY', '')),
        ];
    }
}
