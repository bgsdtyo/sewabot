<?php

namespace App\Services\OtpProviders;

use App\Contracts\OtpProviderInterface;
use App\Models\Setting;
use App\Models\TelegramBot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WahubProvider implements OtpProviderInterface
{
    protected ?string $apiKey = null;

    public function withApiKey(?string $apiKey): static
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey ? trim($apiKey) : null;

        return $clone;
    }

    public function forBot(TelegramBot $bot): static
    {
        $key = $bot->otp_wahub_api_key ?: $bot->otp_api_key;
        if (! filled($key)) {
            throw new RuntimeException('API Key WAHub belum diisi di Konfigurasi Bot.');
        }

        return $this->withApiKey($key);
    }

    protected function resolveApiKey(): string
    {
        if (filled($this->apiKey)) {
            return $this->apiKey;
        }

        $fallback = (string) (Setting::wahubProvider()['api_key'] ?? '');
        if ($fallback !== '') {
            return $fallback;
        }

        throw new RuntimeException('API Key WAHub belum dikonfigurasi.');
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        $base = rtrim((string) (Setting::wahubProvider()['api_base_url'] ?? 'https://dehuyzotp.shop'), '/');

        if ($base === '') {
            $base = 'https://dehuyzotp.shop';
        }

        return Http::baseUrl($base)
            ->withToken($this->resolveApiKey())
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions([
                'connect_timeout' => min(8, $timeout),
            ]);
    }

    public function getServices(int $timeout = 10): array
    {
        $response = $this->client($timeout)->get('/api/services');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil daftar layanan WAHub');
        }

        $items = $response->json();
        if (! is_array($items)) {
            return [];
        }

        // Jika dibungkus key "data"
        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['price'] ?? 0),
                'stock' => (int) ($item['stock'] ?? $item['count'] ?? 0),
                'duration_seconds' => (int) ($item['duration_seconds'] ?? 1200),
            ];
        }

        return $normalized;
    }

    public function createOrder(int $serviceId, ?string $idempotencyKey = null): array
    {
        $response = $this->client(timeout: 20)->post('/api/rent', [
            'service_id' => $serviceId,
        ]);

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwFromResponse($response, 'Gagal sewa nomor OTP WAHub');
        }

        $data = $response->json() ?? [];
        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data, $data['data']);
        }

        $phone = (string) ($data['phone'] ?? $data['phone_number'] ?? '');
        $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : null;

        return [
            'id' => (string) ($data['order_id'] ?? $data['id'] ?? ''),
            'token' => (string) ($data['token'] ?? ''),
            'phone_number' => $phoneFormatted,
            'status' => 'pending',
            'expire_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            'raw' => $data,
        ];
    }

    public function getOrder(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($token) ? $token : $providerOrderId;

        // 1. Coba ambil status lengkap dari /api/order/{id}
        $response = $this->client(timeout: 10)->get('/api/order/'.$identifier);

        if ($response->successful()) {
            $data = $response->json() ?? [];
            if (isset($data['data']) && is_array($data['data'])) {
                $data = array_merge($data, $data['data']);
            }

            return $this->normalizeOrderPayload($data, $providerOrderId, $token);
        }

        // 2. Fallback cek cepat via /api/sms/{token} jika token tersedia
        if (filled($token)) {
            $smsRes = $this->client(timeout: 8)->get('/api/sms/'.$token.'?timeout=5');
            if ($smsRes->successful()) {
                $smsData = $smsRes->json() ?? [];
                $state = strtolower((string) ($smsData['state'] ?? ''));

                if ($state === 'success') {
                    return [
                        'id' => $providerOrderId,
                        'token' => $token,
                        'phone_number' => null,
                        'status' => 'completed',
                        'otp_code' => (string) ($smsData['otp'] ?? ''),
                        'full_text' => (string) ($smsData['otp'] ?? ''),
                        'expire_at' => null,
                        'cancel_reason' => null,
                        'raw' => $smsData,
                    ];
                }
            }
        }

        if ($response->status() === 404 || $response->status() === 410) {
            return [
                'id' => $providerOrderId,
                'token' => $token,
                'status' => 'expired',
                'otp_code' => null,
                'full_text' => null,
                'cancel_reason' => 'Sewa nomor telah berakhir di server WAHub.',
                'raw' => $response->json(),
            ];
        }

        $this->throwFromResponse($response, 'Gagal cek status pesanan WAHub');
    }

    public function cancelOrder(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($providerOrderId) ? $providerOrderId : $token;

        // POST /api/order/{id} dengan action = cancel
        $response = $this->client()->post('/api/order/'.$identifier, [
            'action' => 'cancel',
        ]);

        if (! $response->successful() && filled($token)) {
            // Fallback ke legacy DELETE /api/rent/{token}
            $delRes = $this->client()->delete('/api/rent/'.$token);
            if ($delRes->successful()) {
                return $delRes->json() ?? ['ok' => true];
            }
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal batalkan sewa nomor WAHub');
        }

        return $response->json() ?? ['ok' => true];
    }

    public function resendOtp(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($token) ? $token : $providerOrderId;

        $response = $this->client()->post('/api/rent/'.$identifier.'/retry');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal minta ulang OTP WAHub (Re-OTP)');
        }

        $data = $response->json() ?? [];

        return $data;
    }

    public function changeNumber(string $providerOrderId, ?string $token = null, ?int $serviceId = null): array
    {
        // WAHub tidak memiliki direct change endpoint -> lakukan cancel nomor lama + rent nomor baru
        try {
            $this->cancelOrder($providerOrderId, $token);
        } catch (\Throwable $e) {
            Log::warning('WAHub changeNumber cancel old order warning: '.$e->getMessage());
        }

        if (! $serviceId) {
            throw new RuntimeException('ID Layanan diperlukan untuk ganti nomor WAHub.');
        }

        return $this->createOrder($serviceId);
    }

    public function doneOrder(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($providerOrderId) ? $providerOrderId : $token;

        try {
            $response = $this->client()->post('/api/order/'.$identifier, [
                'action' => 'done',
            ]);

            return $response->json() ?? ['ok' => true];
        } catch (\Throwable $e) {
            Log::warning('WAHub doneOrder warning: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBalance(): array
    {
        $response = $this->client()->get('/api/balance');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek saldo akun WAHub');
        }

        $data = $response->json() ?? [];
        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data, $data['data']);
        }

        $balance = (int) ($data['balance'] ?? 0);
        $available = (int) ($data['available'] ?? $balance);
        $reserved = (int) ($data['reserved'] ?? 0);

        return [
            'balance' => $available,
            'available' => $available,
            'reserved' => $reserved,
            'currency' => 'IDR',
        ];
    }

    public function pingLatency(): array
    {
        $started = microtime(true);

        try {
            $response = $this->client(timeout: 6)->get('/api/balance');
            $ms = (microtime(true) - $started) * 1000;

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'ms' => $ms,
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            return ['ok' => true, 'ms' => $ms, 'error' => null];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'ms' => (microtime(true) - $started) * 1000,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function normalizeOrderPayload(array $data, string $fallbackId, ?string $fallbackToken): array
    {
        $state = strtolower((string) ($data['state'] ?? $data['status'] ?? 'pending'));

        $status = 'pending';
        if ($state === 'success' || filled($data['otp'] ?? null)) {
            $status = 'completed';
        } elseif (in_array($state, ['cancelled', 'canceled', 'cancel', 'refunded'], true)) {
            $status = 'cancelled';
        } elseif (in_array($state, ['expired', 'timeout', 'gone'], true)) {
            $status = 'expired';
        }

        $phone = (string) ($data['phone'] ?? $data['phone_number'] ?? '');
        $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : null;

        return [
            'id' => (string) ($data['order_id'] ?? $data['id'] ?? $fallbackId),
            'token' => (string) ($data['token'] ?? $fallbackToken),
            'phone_number' => $phoneFormatted,
            'status' => $status,
            'otp_code' => (string) ($data['otp'] ?? $data['otp_code'] ?? $data['code'] ?? ''),
            'full_text' => (string) ($data['otp'] ?? $data['sms'] ?? ''),
            'expire_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            'cancel_reason' => $data['cancel_reason'] ?? $data['reason'] ?? null,
            'raw' => $data,
        ];
    }

    protected function throwFromResponse($response, string $fallback): void
    {
        $status = $response->status();
        $body = $response->json();
        $rawMessage = $body['message'] ?? $body['error'] ?? $body['errors'] ?? null;

        if (is_array($rawMessage)) {
            $message = implode(', ', array_filter(array_map('strval', $rawMessage)));
        } elseif (is_string($rawMessage) && trim($rawMessage) !== '') {
            $message = trim($rawMessage);
        } else {
            $message = $fallback." (HTTP {$status})";
        }

        if ($status === 503 || stripos($message, 'stok') !== false || stripos($message, 'stock') !== false) {
            $message = 'Stok nomor WAHub untuk layanan ini sedang habis. Silakan coba beberapa saat lagi.';
        } elseif ($status === 409) {
            $message = 'Sewa nomor WAHub telah kedaluwarsa atau batas permintaan ulang tercapai.';
        } elseif ($status === 429) {
            $message = 'Batas maksimum sewa bersamaan WAHub tercapai. Silakan selesaikan sewa lama terlebih dahulu.';
        } elseif ($status === 401) {
            $message = 'API Key WAHub tidak valid atau kedaluwarsa. Periksa kembali di Pengaturan Bot.';
        }

        Log::warning('WAHub provider error', [
            'status' => $status,
            'body' => $response->body(),
            'parsed_message' => $message,
        ]);

        throw new RuntimeException($message);
    }
}
