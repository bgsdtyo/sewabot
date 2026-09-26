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
            ->withHeaders([
                'Connection' => 'keep-alive',
            ])
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions([
                'connect_timeout' => min(4, $timeout),
            ]);
    }

    public function getServices(int $timeout = 10): array
    {
        try {
            $response = $this->client($timeout)->get('/api/services');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'ambil daftar layanan');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil daftar layanan');
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
        $maxAttempts = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $this->client(timeout: 20)->post('/api/rent', [
                    'service_id' => $serviceId,
                ]);

                $status = $response->status();
                $data = $response->json() ?? [];
                $isExplicitError = isset($data['status']) && ($data['status'] === false || $data['status'] === 'error' || $data['status'] === 'failed');

                if (! in_array($status, [200, 201], true) || $isExplicitError) {
                    $rawMsg = (string) ($data['message'] ?? $data['error'] ?? $data['errors'] ?? '');
                    if (is_array($data['error'] ?? null)) {
                        $rawMsg = (string) ($data['error']['message'] ?? $rawMsg);
                    }

                    $isTransient = $status >= 500
                        || stripos($rawMsg, 'db error') !== false
                        || stripos($rawMsg, 'database') !== false
                        || stripos($rawMsg, 'deadlock') !== false
                        || stripos($rawMsg, 'lock') !== false
                        || stripos($rawMsg, 'timeout') !== false
                        || stripos($rawMsg, 'try again') !== false;

                    if ($isTransient && $attempt < $maxAttempts) {
                        Log::info("WAHub createOrder transient error ({$rawMsg}), retrying attempt {$attempt}/{$maxAttempts} in 500ms...");
                        usleep(500000);
                        continue;
                    }

                    $this->throwFromResponse($response, 'Gagal membuat pesanan nomor OTP');
                }

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
            } catch (\Throwable $e) {
                $lastException = $e;
                $msg = $e->getMessage();
                $isTransient = stripos($msg, 'cURL error') !== false
                    || stripos($msg, 'timed out') !== false
                    || stripos($msg, 'timeout') !== false
                    || stripos($msg, 'db error') !== false
                    || stripos($msg, 'Connection refused') !== false
                    || stripos($msg, 'Connection reset') !== false;

                if ($isTransient && $attempt < $maxAttempts) {
                    Log::info("WAHub createOrder connection/transient error ({$msg}), retrying attempt {$attempt}/{$maxAttempts} in 500ms...");
                    usleep(500000);
                    continue;
                }

                if ($e instanceof RuntimeException) {
                    throw $e;
                }

                $this->handleHttpException($e, 'sewa nomor');
            }
        }

        if ($lastException instanceof RuntimeException) {
            throw $lastException;
        }

        throw new RuntimeException('Gagal membuat pesanan nomor OTP setelah beberapa percobaan.');
    }

    public function getOrder(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($providerOrderId) ? $providerOrderId : $token;

        // 1. Coba ambil status lengkap dari /api/order/{id} jika identifier tersedia
        if (filled($identifier)) {
            try {
                $response = $this->client(timeout: 10)->get('/api/order/'.$identifier);
                if ($response->successful()) {
                    $json = $response->json() ?? [];
                    $data = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : $json;
                    $data['raw'] = $json;

                    return $this->normalizeOrderPayload($data, $providerOrderId, $token);
                }
            } catch (\Throwable $e) {
                $this->handleHttpException($e, 'cek status pesanan');
            }
        }

        // 2. Coba cek status via /api/sms/{token} jika token tersedia
        if (filled($token)) {
            try {
                $smsRes = $this->client(timeout: 8)->get('/api/sms/'.$token.'?timeout=5');
                if ($smsRes->successful()) {
                    $smsJson = $smsRes->json() ?? [];
                    $smsData = (isset($smsJson['data']) && is_array($smsJson['data'])) ? $smsJson['data'] : $smsJson;
                    $smsData['raw'] = $smsJson;

                    return $this->normalizeOrderPayload($smsData, $providerOrderId, $token);
                }

                if ($smsRes->status() === 404 || $smsRes->status() === 410) {
                    return [
                        'id' => $providerOrderId,
                        'token' => $token,
                        'status' => 'expired',
                        'otp_code' => null,
                        'full_text' => null,
                        'cancel_reason' => 'Sewa nomor telah berakhir.',
                        'raw' => $smsRes->json(),
                    ];
                }
            } catch (\Throwable $e) {
                $this->handleHttpException($e, 'cek sms pesanan');
            }
        }

        // 3. Fallback jika response dari /api/order adalah 404/410 dan token tidak ada
        if (isset($response) && ($response->status() === 404 || $response->status() === 410)) {
            return [
                'id' => $providerOrderId,
                'token' => $token,
                'status' => 'expired',
                'otp_code' => null,
                'full_text' => null,
                'cancel_reason' => 'Sewa nomor telah berakhir.',
                'raw' => $response->json(),
            ];
        }

        if (isset($response)) {
            $this->throwFromResponse($response, 'Gagal cek status pesanan');
        }

        throw new RuntimeException('Data pesanan tidak valid untuk dicek.');
    }

    public function cancelOrder(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($providerOrderId) ? $providerOrderId : $token;

        try {
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
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'batalkan pesanan');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal membatalkan pesanan');
        }

        return $response->json() ?? ['ok' => true];
    }

    public function resendOtp(string $providerOrderId, ?string $token = null): array
    {
        $identifier = filled($token) ? $token : $providerOrderId;

        try {
            $response = $this->client()->post('/api/rent/'.$identifier.'/retry');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'minta ulang OTP');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal minta ulang OTP');
        }

        $data = $response->json() ?? [];

        return $data;
    }

    public function changeNumber(string $providerOrderId, ?string $token = null, ?int $serviceId = null): array
    {
        if (! $serviceId) {
            throw new RuntimeException('ID Layanan diperlukan untuk ganti nomor.');
        }

        // Buat order nomor baru terlebih dahulu agar respon instan
        $newOrder = $this->createOrder($serviceId);

        // Batalkan nomor lama secara asynchronous / non-blocking agar tidak menahan latensi
        try {
            $identifier = filled($providerOrderId) ? $providerOrderId : $token;
            if (filled($identifier)) {
                $this->client(timeout: 4)->post('/api/order/'.$identifier, [
                    'action' => 'cancel',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WAHub changeNumber background cancel old order notice: '.$e->getMessage());
        }

        return $newOrder;
    }

    public function doneOrder(string $providerOrderId, ?string $token = null): array
    {
        // Catatan: Tidak memanggil action: done ke server WAHub agar sewa tetap aktif untuk permintaan Re-OTP berikutnya hingga batas sewa berakhir.
        return ['ok' => true];
    }

    public function getBalance(): array
    {
        try {
            $response = $this->client()->get('/api/balance');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'cek saldo');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek saldo server');
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
        $rawState = strtolower((string) ($data['state'] ?? $data['status'] ?? $data['order_status'] ?? 'pending'));
        $otp = $data['otp'] ?? $data['otp_code'] ?? null;
        $hasOtp = filled($otp);

        $status = 'pending';
        if ($hasOtp) {
            $status = 'completed';
        } elseif (in_array($rawState, ['expired', 'timeout', 'gone', 'expire'], true)) {
            $status = 'expired';
        } elseif (in_array($rawState, ['cancelled', 'canceled', 'cancel', 'refunded', 'rejected', 'banned', 'blocked', 'failed'], true)) {
            $status = 'cancelled';
        } elseif (in_array($rawState, ['success', 'completed', 'done'], true)) {
            $status = $hasOtp ? 'completed' : 'pending';
        }

        $phone = (string) ($data['phone'] ?? $data['phone_number'] ?? '');
        $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : null;

        return [
            'id' => (string) ($data['order_id'] ?? $data['id'] ?? $fallbackId),
            'token' => (string) ($data['token'] ?? $fallbackToken),
            'phone_number' => $phoneFormatted,
            'status' => $status,
            'otp_code' => $hasOtp ? (string) $otp : null,
            'full_text' => (string) ($data['sms'] ?? $data['sms_text'] ?? $data['full_text'] ?? $otp ?? ''),
            'expire_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            'cancel_reason' => $data['cancel_reason'] ?? $data['reason'] ?? null,
            'raw' => $data,
        ];
    }

    protected function handleHttpException(\Throwable $e, string $action = 'pemesanan'): never
    {
        $msg = $e->getMessage();
        Log::warning("WAHub {$action} connection error: {$msg}");

        if (
            stripos($msg, 'cURL error 28') !== false
            || stripos($msg, 'timed out') !== false
            || stripos($msg, 'timeout') !== false
            || stripos($msg, 'Resolving timed out') !== false
        ) {
            throw new RuntimeException('Server pemesanan nomor sedang sibuk (koneksi timeout). Silakan coba pesan kembali.');
        }

        if (
            stripos($msg, 'cURL error') !== false
            || stripos($msg, 'Could not resolve host') !== false
            || stripos($msg, 'Failed to connect') !== false
            || stripos($msg, 'Connection refused') !== false
        ) {
            throw new RuntimeException('Gagal terhubung ke server pemesanan nomor. Silakan coba beberapa saat lagi.');
        }

        throw new RuntimeException('Terjadi gangguan koneksi ke server pemesanan. Silakan coba beberapa saat lagi.');
    }

    protected function throwFromResponse($response, string $fallback): void
    {
        $status = $response->status();
        $body = $response->json();
        $rawMessage = $body['message'] ?? $body['error'] ?? $body['errors'] ?? null;

        $hasServerMessage = false;
        if (is_array($rawMessage)) {
            $message = implode(', ', array_filter(array_map('strval', $rawMessage)));
            $hasServerMessage = trim($message) !== '';
        } elseif (is_string($rawMessage) && trim($rawMessage) !== '') {
            $message = trim($rawMessage);
            $hasServerMessage = true;
        } else {
            $message = $fallback." (HTTP {$status})";
        }

        // Filter out URLs and backend domain names
        $message = preg_replace('/https?:\/\/[^\s<>\'"]+/i', '', $message);
        $message = str_ireplace(['dehuyzotp.shop', 'dehuyzotp', 'wahub'], '', $message);
        $message = trim($message);

        if (
            stripos($message, 'balance') !== false ||
            stripos($message, 'saldo') !== false ||
            stripos($message, 'insufficient') !== false ||
            stripos($message, 'not enough') !== false
        ) {
            $message = 'tidak dapat diproses, silakan hubungi admin';
        } elseif (
            stripos($message, 'db error') !== false ||
            stripos($message, 'database error') !== false ||
            stripos($message, 'sql') !== false
        ) {
            $message = 'Server pemesanan nomor sedang sibuk/gangguan sementara. Silakan coba beberapa saat lagi.';
        } elseif ($status === 503 || stripos($message, 'stok') !== false || stripos($message, 'stock') !== false) {
            $message = 'Stok nomor untuk layanan ini sedang habis. Silakan coba beberapa saat lagi.';
        } elseif ($status === 409 && ! $hasServerMessage) {
            $message = 'Sewa nomor telah kedaluwarsa atau batas permintaan ulang tercapai.';
        } elseif ($status === 429 && ! $hasServerMessage) {
            $message = 'Batas maksimum sewa bersamaan tercapai. Silakan selesaikan sewa lama terlebih dahulu.';
        } elseif ($status === 401 && ! $hasServerMessage) {
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
