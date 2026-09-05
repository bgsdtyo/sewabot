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

class KopkenProvider implements OtpProviderInterface
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
        $key = $bot->otp_api_key;
        if (! filled($key)) {
            throw new RuntimeException('API Key EngineUnicorn belum diisi di Konfigurasi Bot.');
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

        throw new RuntimeException('API Key EngineUnicorn belum dikonfigurasi.');
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        $base = rtrim((string) (Setting::otpProvider()['api_base_url'] ?? ''), '/');

        if ($base === '') {
            throw new RuntimeException('EngineUnicorn API Base URL belum diisi di OTP Provider Settings.');
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

    public function getServices(int $timeout = 5): array
    {
        $response = $this->client($timeout)->get('/services');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil daftar layanan Kopken');
        }

        $items = $response->json('data') ?? [];
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['price'] ?? 0),
                'stock' => (int) ($item['stock'] ?? $item['count'] ?? $item['available'] ?? 0),
                'duration_seconds' => (int) ($item['duration_seconds'] ?? 1200),
            ];
        }

        return $normalized;
    }

    public function createOrder(int $serviceId, ?string $idempotencyKey = null): array
    {
        $key = $idempotencyKey ?: (string) Str::uuid();

        $response = $this->client(timeout: 20)
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('/orders', ['service_id' => $serviceId]);

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwFromResponse($response, 'Gagal buat pesanan OTP Kopken');
        }

        $data = $response->json('data') ?? [];
        $data['_idempotency_key'] = $key;

        return [
            'id' => (string) ($data['id'] ?? ''),
            'token' => null,
            'phone_number' => $data['phone_number'] ?? null,
            'status' => strtolower((string) ($data['status'] ?? 'pending')),
            'expire_at' => isset($data['expire_at']) ? (int) $data['expire_at'] : null,
            'raw' => $data,
        ];
    }

    public function getOrder(string $providerOrderId, ?string $token = null): array
    {
        $response = $this->client(timeout: 8)->get('/orders/'.$providerOrderId);

        if (! $response->successful()) {
            $body = $response->json();
            $data = $body['data'] ?? [];
            $errMsg = $body['message'] ?? $body['error'] ?? '';

            if (is_array($data) && ! empty($data)) {
                $data['status'] = $data['status'] ?? 'cancelled';
                $data['cancel_reason'] = $data['cancel_reason'] ?? (is_string($errMsg) ? $errMsg : null);

                return $this->normalizeOrderPayload($data);
            }

            $this->throwFromResponse($response, 'Gagal cek status pesanan Kopken');
        }

        return $this->normalizeOrderPayload($this->unwrapOrderPayload($response->json()));
    }

    public function cancelOrder(string $providerOrderId, ?string $token = null): array
    {
        $response = $this->client()->post('/orders/'.$providerOrderId.'/cancel');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal batalkan pesanan Kopken');
        }

        return $response->json('data') ?? [];
    }

    public function changeNumber(string $providerOrderId, ?string $token = null, ?int $serviceId = null): array
    {
        $key = (string) Str::uuid();

        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('/orders/'.$providerOrderId.'/change');

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwFromResponse($response, 'Gagal ganti nomor Kopken');
        }

        $data = $response->json('data') ?? [];

        return [
            'id' => (string) ($data['id'] ?? $providerOrderId),
            'token' => null,
            'phone_number' => $data['phone_number'] ?? null,
            'status' => strtolower((string) ($data['status'] ?? 'pending')),
            'expire_at' => isset($data['expire_at']) ? (int) $data['expire_at'] : null,
            'raw' => $data,
        ];
    }

    public function resendOtp(string $providerOrderId, ?string $token = null): array
    {
        $response = $this->client()->post('/orders/'.$providerOrderId.'/resend');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal minta ulang OTP Kopken');
        }

        return $response->json('data') ?? [];
    }

    public function doneOrder(string $providerOrderId, ?string $token = null): array
    {
        return ['ok' => true];
    }

    public function getBalance(): array
    {
        $response = $this->client()->get('/balance');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek saldo pusat Kopken');
        }

        $data = $response->json('data') ?? [];
        $balance = (int) ($data['balance'] ?? 0);

        return [
            'balance' => $balance,
            'available' => $balance,
            'reserved' => 0,
            'currency' => (string) ($data['currency'] ?? 'IDR'),
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

    protected function normalizeOrderPayload(array $data): array
    {
        return [
            'id' => (string) ($data['id'] ?? ''),
            'token' => null,
            'phone_number' => $data['phone_number'] ?? null,
            'status' => strtolower((string) ($data['status'] ?? 'pending')),
            'otp_code' => $data['otp'] ?? $data['otp_code'] ?? $data['code'] ?? null,
            'full_text' => $data['full_text'] ?? $data['sms'] ?? $data['sms_text'] ?? null,
            'expire_at' => isset($data['expire_at']) ? (int) $data['expire_at'] : null,
            'cancel_reason' => $data['cancel_reason'] ?? $data['reason'] ?? null,
            'raw' => $data,
        ];
    }

    protected function unwrapOrderPayload(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            return is_array($json) ? $json : [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data, $data['data']);
        }

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json as $k => $v) {
                if ($k !== 'data' && ! isset($data[$k])) {
                    $data[$k] = $v;
                }
            }
        }

        return $data;
    }

    protected function throwFromResponse($response, string $fallback): void
    {
        $body = $response->json();
        $rawMessage = $body['message'] ?? $body['error'] ?? $body['errors'] ?? null;

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
            $message = $fallback.' (HTTP '.$response->status().')';
        }

        if (
            stripos($message, 'banned') !== false ||
            stripos($message, 'terblokir') !== false ||
            stripos($message, 'blocked') !== false
        ) {
            $message = 'Nomor WhatsApp terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda. Saldo yang tertahan telah dikembalikan.';
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
        } elseif (
            stripos($message, 'balance') !== false ||
            stripos($message, 'saldo') !== false ||
            stripos($message, 'insufficient') !== false ||
            stripos($message, 'not enough') !== false
        ) {
            $message = 'Nomor untuk layanan ini sedang tidak tersedia saat ini. Slot dibatalkan, saldo Anda tidak dipotong. Segera hubungi admin untuk dibantu pengecekan.';
        }

        Log::warning('Kopken provider error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'parsed_message' => $message,
        ]);

        throw new RuntimeException($message);
    }
}
