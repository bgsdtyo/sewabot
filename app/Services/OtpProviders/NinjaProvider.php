<?php

namespace App\Services\OtpProviders;

use App\Contracts\OtpProviderInterface;
use App\Models\Setting;
use App\Models\TelegramBot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NinjaProvider implements OtpProviderInterface
{
    public const DEFAULT_BASE_URL = 'https://app.ninjatop.cloud/api/public/v1';

    protected ?string $apiKey = null;

    public function withApiKey(?string $apiKey): static
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey ? trim($apiKey) : null;

        return $clone;
    }

    public function forBot(TelegramBot $bot): static
    {
        $key = $bot->otp_api_key;
        if (! filled($key)) {
            throw new RuntimeException('API Key Ninja OTP belum diisi di Konfigurasi Bot.');
        }

        return $this->withApiKey($key);
    }

    protected function resolveApiKey(): string
    {
        if (filled($this->apiKey)) {
            return $this->apiKey;
        }

        $fallback = (string) (Setting::otpProvider()['api_key'] ?? '');
        if ($fallback !== '') {
            return $fallback;
        }

        throw new RuntimeException('API Key Ninja OTP belum dikonfigurasi.');
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        $base = rtrim((string) (Setting::otpProvider()['api_base_url'] ?? ''), '/');

        if ($base === '' || str_contains($base, 'engineunicorn')) {
            $base = self::DEFAULT_BASE_URL;
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
            $response = $this->client($timeout)->get('/services');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'ambil daftar layanan');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil daftar layanan');
        }

        $json = $response->json();
        $items = $json['data'] ?? $json ?? [];
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $durationMinutes = (int) ($item['duration'] ?? 20);

            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['price'] ?? 0),
                'stock' => (int) ($item['available_count'] ?? $item['stock'] ?? $item['count'] ?? 0),
                'duration_seconds' => $durationMinutes * 60,
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
            $key = ($attempt === 1 && $idempotencyKey) ? $idempotencyKey : (string) Str::uuid();

            try {
                $response = $this->client(timeout: 20)
                    ->withHeaders(['Idempotency-Key' => $key])
                    ->post('/orders', [
                        'service_id' => $serviceId,
                        'qty' => 1,
                    ]);

                $status = $response->status();
                $json = $response->json() ?? [];
                $isExplicitError = isset($json['status']) && ($json['status'] === false || $json['status'] === 'error' || $json['status'] === 'failed');

                if (! in_array($status, [200, 201], true) || $isExplicitError) {
                    $rawMsg = (string) ($json['message'] ?? $json['error'] ?? $json['errors'] ?? '');
                    if (is_array($json['error'] ?? null)) {
                        $rawMsg = (string) ($json['error']['message'] ?? $rawMsg);
                    }

                    $isTransient = $status >= 500
                        || stripos($rawMsg, 'db error') !== false
                        || stripos($rawMsg, 'database') !== false
                        || stripos($rawMsg, 'deadlock') !== false
                        || stripos($rawMsg, 'lock') !== false
                        || stripos($rawMsg, 'timeout') !== false
                        || stripos($rawMsg, 'try again') !== false;

                    if ($isTransient && $attempt < $maxAttempts) {
                        Log::info("Ninja OTP createOrder transient error ({$rawMsg}), retrying attempt {$attempt}/{$maxAttempts} in 500ms...");
                        usleep(500000);
                        continue;
                    }

                    $this->throwFromResponse($response, 'Gagal membuat pesanan nomor OTP');
                }

                $orderData = [];
                if (isset($json['orders']) && is_array($json['orders']) && ! empty($json['orders'])) {
                    $orderData = $json['orders'][0];
                } elseif (isset($json['data']) && is_array($json['data'])) {
                    $orderData = isset($json['data']['orders'][0]) ? $json['data']['orders'][0] : $json['data'];
                } else {
                    $orderData = $json;
                }

                $orderData['_idempotency_key'] = $key;

                $phone = (string) ($orderData['phone_number'] ?? $orderData['phone'] ?? '');
                $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : null;

                return [
                    'id' => (string) ($orderData['id'] ?? ''),
                    'token' => null,
                    'phone_number' => $phoneFormatted,
                    'status' => strtolower((string) ($orderData['status'] ?? 'pending')),
                    'expire_at' => isset($orderData['expire_at']) ? (int) $orderData['expire_at'] : null,
                    'raw' => $orderData,
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
                    Log::info("Ninja OTP createOrder connection/transient error ({$msg}), retrying attempt {$attempt}/{$maxAttempts} in 500ms...");
                    usleep(500000);
                    continue;
                }

                if ($e instanceof RuntimeException) {
                    throw $e;
                }

                $this->handleHttpException($e, 'buat pesanan');
            }
        }

        if ($lastException instanceof RuntimeException) {
            throw $lastException;
        }

        throw new RuntimeException('Gagal membuat pesanan nomor OTP setelah beberapa percobaan.');
    }

    public function getOrder(string $providerOrderId, ?string $token = null): array
    {
        try {
            $response = $this->client(timeout: 8)->get('/orders/'.$providerOrderId);
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'cek status pesanan');
        }

        if (! $response->successful()) {
            if ($response->status() === 404) {
                return [
                    'id' => $providerOrderId,
                    'token' => null,
                    'status' => 'expired',
                    'otp_code' => null,
                    'full_text' => null,
                    'cancel_reason' => 'Pesanan tidak ditemukan atau telah berakhir.',
                    'raw' => $response->json(),
                ];
            }

            $this->throwFromResponse($response, 'Gagal cek status pesanan');
        }

        $json = $response->json() ?? [];
        $data = (isset($json['data']) && is_array($json['data'])) ? $json['data'] : $json;

        return $this->normalizeOrderPayload($data, $providerOrderId);
    }

    public function cancelOrder(string $providerOrderId, ?string $token = null): array
    {
        try {
            $response = $this->client()->post('/orders/'.$providerOrderId.'/cancel');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'batalkan pesanan');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal membatalkan pesanan');
        }

        return $response->json() ?? ['status' => 'cancelled'];
    }

    public function changeNumber(string $providerOrderId, ?string $token = null, ?int $serviceId = null): array
    {
        if (! $serviceId) {
            throw new RuntimeException('ID Layanan diperlukan untuk ganti nomor.');
        }

        // Buat order nomor baru terlebih dahulu
        $newOrder = $this->createOrder($serviceId);

        // Batalkan nomor lama secara asynchronous / non-blocking
        try {
            if (filled($providerOrderId)) {
                $this->client(timeout: 4)->post('/orders/'.$providerOrderId.'/cancel');
            }
        } catch (\Throwable $e) {
            Log::warning('Ninja OTP changeNumber background cancel old order notice: '.$e->getMessage());
        }

        return $newOrder;
    }

    public function resendOtp(string $providerOrderId, ?string $token = null): array
    {
        try {
            $response = $this->client()->post('/orders/'.$providerOrderId.'/resend');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'minta ulang OTP');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal minta ulang OTP');
        }

        return $response->json() ?? ['resent' => true];
    }

    public function doneOrder(string $providerOrderId, ?string $token = null): array
    {
        try {
            $response = $this->client(timeout: 4)->post('/orders/'.$providerOrderId.'/ack');

            return $response->json() ?? ['status' => 'completed'];
        } catch (\Throwable $e) {
            Log::warning('Ninja OTP doneOrder ack failed: '.$e->getMessage());

            return ['ok' => true];
        }
    }

    public function getBalance(): array
    {
        try {
            $response = $this->client()->get('/balance');
        } catch (\Throwable $e) {
            $this->handleHttpException($e, 'cek saldo');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek saldo server');
        }

        $data = $response->json() ?? [];
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        $balance = (int) ($data['balance'] ?? 0);

        return [
            'balance' => $balance,
            'available' => $balance,
            'reserved' => 0,
            'currency' => 'IDR',
        ];
    }

    public function pingLatency(): array
    {
        $started = microtime(true);

        try {
            $response = $this->client(timeout: 6)->get('/balance');
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

    protected function normalizeOrderPayload(array $data, string $fallbackId): array
    {
        $rawState = strtolower((string) ($data['status'] ?? $data['state'] ?? 'pending'));
        $otp = $data['otp_code'] ?? $data['otp'] ?? $data['code'] ?? null;
        $hasOtp = filled($otp);

        $status = 'pending';
        if ($hasOtp || in_array($rawState, ['completed', 'success', 'done'], true)) {
            $status = 'completed';
        } elseif (in_array($rawState, ['expired', 'timeout', 'gone'], true)) {
            $status = 'expired';
        } elseif (in_array($rawState, ['cancelled', 'canceled', 'cancel', 'refunded', 'banned', 'blocked', 'failed'], true)) {
            $status = 'cancelled';
        }

        $phone = (string) ($data['phone_number'] ?? $data['phone'] ?? '');
        $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : null;

        return [
            'id' => (string) ($data['id'] ?? $fallbackId),
            'token' => null,
            'phone_number' => $phoneFormatted,
            'status' => $status,
            'otp_code' => $hasOtp ? (string) $otp : null,
            'full_text' => (string) ($data['full_text'] ?? $data['sms'] ?? $otp ?? ''),
            'expire_at' => isset($data['expire_at']) ? (int) $data['expire_at'] : null,
            'cancel_reason' => $data['cancel_reason'] ?? $data['reason'] ?? null,
            'raw' => $data,
        ];
    }

    protected function handleHttpException(\Throwable $e, string $action = 'pemesanan'): never
    {
        $msg = $e->getMessage();
        Log::warning("Ninja OTP {$action} connection error: {$msg}");

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

        $errorCode = null;
        $rawMessage = null;

        if (isset($body['error']) && is_array($body['error'])) {
            $errorCode = strtoupper((string) ($body['error']['code'] ?? ''));
            $rawMessage = $body['error']['message'] ?? null;
        } else {
            $rawMessage = $body['message'] ?? $body['error'] ?? $body['errors'] ?? null;
        }

        if (is_array($rawMessage)) {
            $flattened = [];
            array_walk_recursive($rawMessage, function ($val) use (&$flattened) {
                if (is_string($val) || is_numeric($val)) {
                    $flattened[] = (string) $val;
                }
            });
            $message = ! empty($flattened) ? implode(', ', $flattened) : (string) json_encode($rawMessage);
        } elseif (is_string($rawMessage) && trim($rawMessage) !== '') {
            $message = trim($rawMessage);
        } else {
            $message = $fallback.' (HTTP '.$status.')';
        }

        // Handle specific Ninja OTP error codes
        if ($errorCode === 'UNAUTHENTICATED' || $status === 401) {
            $message = 'API Key Ninja OTP tidak valid atau belum diisi (wajib format nk_...). Silakan periksa di Konfigurasi Bot.';
        } elseif ($errorCode === 'FORBIDDEN' || $status === 403) {
            $message = 'API Key Ninja OTP tidak memiliki izin atau akses ditolak oleh server.';
        } elseif ($errorCode === 'INSUFFICIENT_BALANCE' || $status === 402) {
            $message = 'Saldo akun Ninja OTP tidak cukup di server provider.';
        } elseif ($errorCode === 'OUT_OF_STOCK' || $status === 503) {
            $message = 'Stok nomor untuk layanan ini sedang habis. Silakan coba beberapa saat lagi.';
        } elseif ($errorCode === 'NOT_FOUND' || $status === 404) {
            $message = 'Data pesanan/layanan tidak ditemukan di server Ninja OTP.';
        } elseif ($errorCode === 'RATE_LIMITED' || $status === 429) {
            $message = 'Server Ninja OTP rate limit (terlalu banyak request). Silakan coba beberapa saat lagi.';
        } elseif ($errorCode === 'API_DISABLED') {
            $message = 'Server Ninja OTP sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.';
        }

        // Filter out URLs and backend domain names
        $message = preg_replace('/https?:\/\/[^\s<>\'"]+/i', '', $message);
        $message = str_ireplace(['ninjatop.cloud', 'ninjatop', 'ninjaotp', 'ninja'], '', $message);
        $message = trim($message);

        if (
            stripos($message, 'banned') !== false ||
            stripos($message, 'terblokir') !== false ||
            stripos($message, 'blocked') !== false
        ) {
            $message = 'Nomor WhatsApp terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda. Saldo yang tertahan telah dikembalikan.';
        } elseif (
            stripos($message, 'db error') !== false ||
            stripos($message, 'database error') !== false ||
            stripos($message, 'sql') !== false
        ) {
            $message = 'Server pemesanan nomor sedang sibuk/gangguan sementara. Silakan coba beberapa saat lagi.';
        } elseif (
            stripos($message, 'out of stock') !== false ||
            stripos($message, 'no stock') !== false ||
            stripos($message, 'stock empty') !== false ||
            stripos($message, 'habis') !== false ||
            stripos($message, 'no number') !== false ||
            stripos($message, 'empty') !== false ||
            stripos($message, 'stok') !== false
        ) {
            $message = 'Stok nomor untuk layanan ini saat ini sedang habis. Silakan coba beberapa saat lagi.';
        }

        Log::warning('Ninja OTP provider error', [
            'status' => $status,
            'body' => $response->body(),
            'parsed_message' => $message,
            'error_code' => $errorCode,
        ]);

        throw new RuntimeException($message);
    }
}
