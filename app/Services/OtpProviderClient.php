<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TelegramBot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OtpProviderClient
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
        if (! filled($bot->otp_api_key)) {
            throw new RuntimeException('API Key provider belum diisi di Konfigurasi Bot.');
        }

        return $this->withApiKey($bot->otp_api_key);
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

        throw new RuntimeException('API Key provider belum dikonfigurasi.');
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        $base = rtrim((string) (Setting::otpProvider()['api_base_url'] ?? ''), '/');

        if ($base === '') {
            throw new RuntimeException('OTP API Base URL belum diisi. Minta admin set di OTP Provider → API Settings.');
        }

        return Http::baseUrl($base)
            ->withToken($this->resolveApiKey())
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions([
                'connect_timeout' => min(8, $timeout),
            ]);
    }

    public function getServices(int $timeout = 5): array
    {
        $response = $this->client($timeout)->get('/services');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil daftar layanan');
        }

        return $response->json('data') ?? [];
    }

    public function createOrder(int $serviceId, ?string $idempotencyKey = null): array
    {
        $key = $idempotencyKey ?: (string) Str::uuid();

        $response = $this->client(timeout: 20)
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('/orders', ['service_id' => $serviceId]);

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwFromResponse($response, 'Gagal buat pesanan OTP');
        }

        $data = $response->json('data') ?? [];
        $data['_idempotency_key'] = $key;

        return $data;
    }

    public function getOrder(string $providerOrderId): array
    {
        $response = $this->client(timeout: 8)->get('/orders/'.$providerOrderId);

        if (! $response->successful()) {
            $body = $response->json();
            $data = $body['data'] ?? [];
            $errMsg = $body['message'] ?? $body['error'] ?? '';

            if (is_array($data) && ! empty($data)) {
                $data['status'] = $data['status'] ?? 'cancelled';
                $data['cancel_reason'] = $data['cancel_reason'] ?? (is_string($errMsg) ? $errMsg : null);

                return $this->unwrapOrderPayload($data);
            }

            $this->throwFromResponse($response, 'Gagal cek status pesanan OTP');
        }

        return $this->unwrapOrderPayload($response->json());
    }

    public function cancelOrder(string $providerOrderId): array
    {
        $response = $this->client()->post('/orders/'.$providerOrderId.'/cancel');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal batalkan pesanan OTP');
        }

        return $response->json('data') ?? [];
    }

    public function changeNumber(string $providerOrderId, ?string $idempotencyKey = null): array
    {
        $key = $idempotencyKey ?: (string) Str::uuid();

        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('/orders/'.$providerOrderId.'/change');

        if (! in_array($response->status(), [200, 201], true)) {
            $this->throwFromResponse($response, 'Gagal ganti nomor OTP');
        }

        return $response->json('data') ?? [];
    }

    public function resendOtp(string $providerOrderId): array
    {
        $response = $this->client()->post('/orders/'.$providerOrderId.'/resend');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal minta ulang OTP');
        }

        return $response->json('data') ?? [];
    }

    /**
     * GET /balance — saldo pusat akun provider (API key bot).
     *
     * @return array{balance: int|float, currency: string}
     */
    public function getBalance(): array
    {
        $response = $this->client()->get('/balance');

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek saldo pusat');
        }

        $data = $response->json('data') ?? [];

        return [
            'balance' => $data['balance'] ?? 0,
            'currency' => (string) ($data['currency'] ?? 'IDR'),
        ];
    }

    /**
     * @param  mixed  $json
     * @return array<string, mixed>
     */
    protected function unwrapOrderPayload(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            return is_array($json) ? $json : [];
        }

        if (isset($data['data']) && is_array($data['data']) && ! isset($data['otp']) && ! isset($data['otp_code']) && ! isset($data['status'])) {
            $data = array_merge($data, $data['data']);
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

        // Friendly Indonesian translations for user
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
        } elseif (stripos($message, 'balance') !== false || stripos($message, 'saldo') !== false) {
            $message = 'Layanan ini sedang dalam proses restock/pemeliharaan. Silakan coba beberapa saat lagi atau hubungi admin.';
        }

        Log::warning('OTP provider error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'parsed_message' => $message,
        ]);

        throw new RuntimeException($message);
    }
}
