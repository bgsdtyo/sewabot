<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service untuk validasi nomor telepon via Kopi Kenangan Phone Checker API.
 *
 * Pricing:
 *  - Nomor TERDAFTAR   : 0 kredit (GRATIS)
 *  - Nomor FRESH/baru  : 1 kredit (Rp100) per nomor
 *
 * Digunakan oleh OtpAutoOrderService setelah nomor diterima dari provider
 * untuk memastikan member hanya mendapatkan nomor yang belum terdaftar.
 */
class KopkenPhoneCheckerService
{
    /**
     * Cek saldo kredit checker dari API profile.
     *
     * @throws RuntimeException
     */
    public function getBalance(?string $apiKey = null, ?string $baseUrl = null): array
    {
        $key = $this->resolveApiKey($apiKey);
        $started = microtime(true);

        try {
            $response = $this->client($key, $baseUrl, timeout: 8)->get('/api/v1/profile');
        } catch (\Throwable $e) {
            $this->handleConnectionError($e, 'cek saldo checker');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal mengambil profil Phone Checker');
        }

        $data = $response->json() ?? [];

        // API boostly.id mengembalikan nested: { "success": true, "data": { "credits": 49, ... } }
        // Support both nested ("data" key) dan flat response
        $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        $credits = (int) ($payload['credits'] ?? $payload['credit'] ?? $payload['balance'] ?? $payload['saldo'] ?? 0);

        return [
            'credits'  => $credits,
            'username' => (string) ($payload['username'] ?? $payload['user'] ?? $payload['first_name'] ?? ''),
            'ms'       => round((microtime(true) - $started) * 1000, 1),
            'raw'      => $data,
        ];
    }

    /**
     * Cek 1 nomor telepon.
     *
     * @return array{phone: string, status: string, is_fresh: bool, raw: array}
     * @throws RuntimeException
     */
    public function checkSingle(string $phone, ?string $apiKey = null, ?string $baseUrl = null): array
    {
        $key = $this->resolveApiKey($apiKey);
        $phone = $this->normalizePhone($phone);

        try {
            $response = $this->client($key, $baseUrl, timeout: 15)->post('/api/v1/check', [
                'phone' => $phone,
            ]);
        } catch (\Throwable $e) {
            $this->handleConnectionError($e, 'cek nomor tunggal');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal cek nomor via Phone Checker');
        }

        $data = $response->json() ?? [];
        $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        return $this->normalizeCheckResult($phone, $payload);
    }

    /**
     * Cek banyak nomor sekaligus (maks 25 per request, otomatis di-chunk).
     *
     * @param  string[]  $phones  Daftar nomor telepon
     * @return array{
     *     fresh: string[],
     *     registered: string[],
     *     results: array<string, array{phone: string, status: string, is_fresh: bool}>,
     *     credits_used: int
     * }
     * @throws RuntimeException
     */
    public function checkBulk(array $phones, ?string $apiKey = null, ?string $baseUrl = null): array
    {
        if (empty($phones)) {
            return [
                'fresh'       => [],
                'registered'  => [],
                'results'     => [],
                'credits_used' => 0,
            ];
        }

        $key = $this->resolveApiKey($apiKey);
        $phones = array_values(array_unique(array_filter(array_map(
            fn ($p) => $this->normalizePhone((string) $p),
            $phones
        ))));

        $allResults   = [];
        $freshPhones  = [];
        $regPhones    = [];
        $creditsUsed  = 0;

        // API hanya menerima maks 25 nomor per request
        $chunks = array_chunk($phones, 25);

        foreach ($chunks as $chunk) {
            try {
                $response = $this->client($key, $baseUrl, timeout: 20)->post('/api/v1/check-bulk', [
                    'phones' => $chunk,
                ]);
            } catch (\Throwable $e) {
                Log::warning('KopkenPhoneChecker bulk check failed: '.$e->getMessage(), [
                    'phones_count' => count($chunk),
                ]);
                // Jika gagal, anggap semua fresh agar auto order tetap berjalan
                foreach ($chunk as $phone) {
                    $freshPhones[] = $phone;
                    $allResults[$phone] = [
                        'phone'   => $phone,
                        'status'  => 'unknown',
                        'is_fresh' => true,
                        'error'   => $e->getMessage(),
                    ];
                }
                continue;
            }

            if (! $response->successful()) {
                Log::warning('KopkenPhoneChecker bulk response error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                // Fallback: anggap fresh agar tidak memblokir auto order
                foreach ($chunk as $phone) {
                    $freshPhones[] = $phone;
                    $allResults[$phone] = [
                        'phone'   => $phone,
                        'status'  => 'unknown',
                        'is_fresh' => true,
                    ];
                }
                continue;
            }

            $data = $response->json() ?? [];
            $items = $data['data'] ?? $data['results'] ?? $data ?? [];

            if (! is_array($items)) {
                $items = [];
            }

            // Normalize hasil dari API (bisa array of objects atau keyed by phone)
            $resultMap = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemPhone = $this->normalizePhone((string) ($item['phone'] ?? $item['number'] ?? $item['nomor'] ?? ''));
                if ($itemPhone === '') {
                    continue;
                }
                $resultMap[$itemPhone] = $this->normalizeCheckResult($itemPhone, $item);
            }

            foreach ($chunk as $phone) {
                if (isset($resultMap[$phone])) {
                    $result = $resultMap[$phone];
                } else {
                    // Nomor tidak ada di response, anggap fresh (safe default)
                    $result = [
                        'phone'   => $phone,
                        'status'  => 'unknown',
                        'is_fresh' => true,
                    ];
                }

                $allResults[$phone] = $result;

                if ($result['is_fresh']) {
                    $freshPhones[] = $phone;
                    $creditsUsed++;
                } else {
                    $regPhones[] = $phone;
                }
            }
        }

        return [
            'fresh'       => array_values($freshPhones),
            'registered'  => array_values($regPhones),
            'results'     => $allResults,
            'credits_used' => $creditsUsed,
        ];
    }

    /**
     * Cek riwayat pengecekan (opsional, berguna untuk deduplication/cache manual).
     */
    public function getHistory(?string $date = null, int $limit = 100, ?string $apiKey = null, ?string $baseUrl = null): array
    {
        $key = $this->resolveApiKey($apiKey);

        $params = ['limit' => $limit];
        if ($date) {
            $params['date'] = $date;
        }

        try {
            $response = $this->client($key, $baseUrl, timeout: 10)->get('/api/v1/history', $params);
        } catch (\Throwable $e) {
            $this->handleConnectionError($e, 'ambil riwayat checker');
        }

        if (! $response->successful()) {
            $this->throwFromResponse($response, 'Gagal ambil riwayat Phone Checker');
        }

        $data = $response->json() ?? [];

        return $data['data'] ?? $data['results'] ?? $data ?? [];
    }

    /**
     * Ping latency ke server checker.
     */
    public function pingLatency(?string $apiKey = null, ?string $baseUrl = null): array
    {
        $started = microtime(true);

        try {
            $key = $this->resolveApiKey($apiKey);
            $response = $this->client($key, $baseUrl, timeout: 6)->get('/api/v1/profile');
            $ms = (microtime(true) - $started) * 1000;

            return [
                'ok'    => $response->successful(),
                'ms'    => round($ms, 1),
                'error' => $response->successful() ? null : 'HTTP '.$response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'    => false,
                'ms'    => round((microtime(true) - $started) * 1000, 1),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ambil API key dari parameter (konfigurasi bot) atau fallback ke environment.
     */
    protected function resolveApiKey(?string $apiKey = null): string
    {
        if (filled($apiKey)) {
            return trim($apiKey);
        }

        $fromEnv = (string) env('PHONE_CHECKER_API_KEY', '');
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        throw new RuntimeException('API Key Phone Checker belum diisi di Konfigurasi Bot.');
    }

    /**
     * Resolusi Base URL API Phone Checker dari bot atau environment.
     */
    protected function resolveBaseUrl(?string $baseUrl = null): string
    {
        $url = trim((string) ($baseUrl ?: env('PHONE_CHECKER_BASE_URL', '')));
        if ($url === '') {
            throw new RuntimeException('Base URL Phone Checker belum diisi. Silakan isi URL API Phone Checker di Konfigurasi Bot.');
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    /**
     * Build HTTP client dengan X-API-Key header (bukan Bearer token).
     */
    protected function client(string $apiKey, ?string $baseUrl = null, int $timeout = 15): \Illuminate\Http\Client\PendingRequest
    {
        $base = $this->resolveBaseUrl($baseUrl);

        return Http::baseUrl($base)
            ->withHeaders([
                'X-API-Key'  => $apiKey,
                'Connection' => 'keep-alive',
            ])
            ->withQueryParameters([
                'api_key' => $apiKey, // fallback: beberapa endpoint support query param
            ])
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions([
                'connect_timeout' => min(4, $timeout),
            ]);
    }

    /**
     * Normalisasi format nomor telepon ke format 08xxx atau 628xxx.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone));
        if ($phone === '') {
            return '';
        }

        // Sudah format 62xxx → biarkan
        if (str_starts_with($phone, '62')) {
            return $phone;
        }

        // Format 08xxx → jadikan 628xxx
        if (str_starts_with($phone, '0')) {
            return '62'.ltrim($phone, '0');
        }

        // Format 8xxx → tambah 62
        if (str_starts_with($phone, '8')) {
            return '62'.$phone;
        }

        return $phone;
    }

    /**
     * Normalisasi hasil cek dari response API ke format standar internal.
     *
     * @return array{phone: string, status: string, is_fresh: bool, raw: array}
     */
    protected function normalizeCheckResult(string $phone, array $data): array
    {
        // Ambil status dari berbagai kemungkinan key
        $rawStatus = strtolower(trim((string) (
            $data['status'] ?? $data['result'] ?? $data['state'] ?? ''
        )));

        // "TERDAFTAR" / "registered" / "exists" → nomor sudah terdaftar, BUKAN fresh
        // "BELUM TERDAFTAR" / "fresh" / "not_registered" / "new" → nomor fresh
        $isFresh = match (true) {
            str_contains($rawStatus, 'fresh')           => true,
            str_contains($rawStatus, 'belum')           => true,
            str_contains($rawStatus, 'not_registered')  => true,
            str_contains($rawStatus, 'not registered')  => true,
            str_contains($rawStatus, 'unregistered')    => true,
            str_contains($rawStatus, 'new')             => true,
            str_contains($rawStatus, 'terdaftar')       => false, // "TERDAFTAR" = sudah terdaftar
            str_contains($rawStatus, 'registered')      => false,
            str_contains($rawStatus, 'exists')          => false,
            str_contains($rawStatus, 'found')           => false,
            default                                     => true,  // unknown → safe default: anggap fresh
        };

        // Override: kalau ada field boolean eksplisit
        if (isset($data['is_fresh'])) {
            $isFresh = (bool) $data['is_fresh'];
        } elseif (isset($data['fresh'])) {
            $isFresh = (bool) $data['fresh'];
        } elseif (isset($data['registered'])) {
            $isFresh = ! ((bool) $data['registered']);
        }

        return [
            'phone'   => $phone,
            'status'  => $rawStatus ?: 'unknown',
            'is_fresh' => $isFresh,
            'raw'     => $data,
        ];
    }

    protected function handleConnectionError(\Throwable $e, string $action = 'checker'): never
    {
        $msg = $e->getMessage();
        Log::warning("KopkenPhoneChecker {$action} error: {$msg}");

        if (stripos($msg, 'timed out') !== false || stripos($msg, 'timeout') !== false) {
            throw new RuntimeException('Phone Checker API timeout. Auto order tetap dilanjutkan tanpa validasi nomor.');
        }

        throw new RuntimeException('Gagal terhubung ke Phone Checker API: '.$msg);
    }

    protected function throwFromResponse(\Illuminate\Http\Client\Response $response, string $fallback): never
    {
        $status = $response->status();
        $body   = $response->json() ?? [];
        $message = (string) ($body['message'] ?? $body['error'] ?? $body['detail'] ?? $fallback.' (HTTP '.$status.')');

        if ($status === 401) {
            $message = 'Phone Checker API Key tidak valid. Silakan periksa konfigurasi.';
        } elseif ($status === 402) {
            $message = 'Kredit Phone Checker habis. Silakan isi ulang kredit.';
        } elseif ($status === 429) {
            $message = 'Phone Checker rate limit. Terlalu banyak request.';
        }

        Log::warning('KopkenPhoneChecker API error', [
            'status'  => $status,
            'message' => $message,
            'body'    => $response->body(),
        ]);

        throw new RuntimeException($message);
    }
}

