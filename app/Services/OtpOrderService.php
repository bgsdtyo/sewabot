<?php

namespace App\Services;

use App\Models\BotMember;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\Setting;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OtpOrderService
{
    public function __construct(
        protected OtpProviderManager $providerManager,
        protected WalletService $wallet,
        protected TelegramBotService $telegram
    ) {}

    /**
     * Sync services specifically for KOPKEN / WhatsApp from the active provider.
     */
    public function syncServices(?array $onlyNames = ['KOPKEN', 'WHATSAPP'], ?TelegramBot $usingBot = null, ?string $providerName = null): int
    {
        $targetProvider = $usingBot
            ? $usingBot->activeOtpProvider()
            : ($providerName ?: Setting::activeOtpProvider());

        $client = $usingBot
            ? $this->providerManager->forBot($usingBot)
            : $this->providerManager->driver($targetProvider);

        $items = $client->getServices();
        Log::info("Syncing services for provider [{$targetProvider}] - items count: ".count($items), ['items' => $items]);
        // Cek apakah provider memiliki layanan spesifik 'KOPKEN' / 'KOPI KENANGAN'
        $hasKopkenSpecific = collect($items)->contains(function ($item) {
            $n = strtoupper(trim((string) ($item['name'] ?? '')));
            return str_contains($n, 'KOPKEN') || str_contains($n, 'KOPI KENANGAN') || str_contains($n, 'KOPIKENANGAN');
        });

        $count = 0;
        foreach ($items as $item) {
            $name = (string) ($item['name'] ?? '');
            $nameUpper = strtoupper(trim($name));

            $isMatched = false;
            if ($hasKopkenSpecific) {
                // Hanya ambil layanan KOPKEN
                if (str_contains($nameUpper, 'KOPKEN') || str_contains($nameUpper, 'KOPI KENANGAN') || str_contains($nameUpper, 'KOPIKENANGAN')) {
                    $isMatched = true;
                }
            } else {
                // Fallback jika provider menamai layanannya WhatsApp
                if ($nameUpper === 'WHATSAPP' || str_starts_with($nameUpper, 'WHATSAPP') || in_array($nameUpper, ['WA', 'WHATSAPP'], true) || count($items) === 1) {
                    $isMatched = true;
                }
            }

            if (! $isMatched) {
                // Nonaktifkan service lain jika sempat tersimpan
                OtpService::where('provider', $targetProvider)
                    ->where('provider_service_id', (int) $item['id'])
                    ->update(['is_active' => false, 'is_enabled' => false]);

                continue;
            }

            $providerPrice = (int) ($item['price'] ?? 0);

            OtpService::updateOrCreate(
                [
                    'provider' => $targetProvider,
                    'provider_service_id' => (int) $item['id'],
                ],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'provider_price' => $providerPrice,
                    'sell_price' => $providerPrice,
                    'duration_seconds' => (int) ($item['duration_seconds'] ?? 1200),
                    'stock' => (int) ($item['stock'] ?? 0),
                    'is_active' => true,
                    'is_enabled' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    public function getServiceStock(OtpService $service, ?TelegramBot $bot = null, bool $forceFresh = false): int
    {
        if (! $forceFresh) {
            return (int) ($service->stock ?? 0);
        }

        if (! $bot || ! $bot->hasOtpConfigured()) {
            return (int) ($service->stock ?? 0);
        }

        $providerName = $service->provider ?: $bot->activeOtpProvider();
        $cacheKey = "bot_{$bot->id}_svc_{$providerName}_{$service->provider_service_id}_stock";

        try {
            $client = $this->providerManager->forBot($bot);
            $services = $client->getServices(timeout: 5);
            foreach ($services as $item) {
                if ((int) ($item['id'] ?? 0) === (int) $service->provider_service_id) {
                    $stock = (int) ($item['stock'] ?? 0);
                    $service->update(['stock' => $stock]);
                    cache()->put($cacheKey, $stock, now()->addSeconds(60));

                    return $stock;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Live stock fetch timeout/error: '.$e->getMessage());
        }

        return (int) ($service->stock ?? 0);
    }

    public function findOrRegisterMember(TelegramBot $bot, array $from): BotMember
    {
        $chatId = (string) ($from['id'] ?? '');
        if ($chatId === '') {
            throw new RuntimeException('Chat ID tidak valid.');
        }

        $username = $from['username'] ?? null;
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: null;

        $member = BotMember::firstOrCreate(
            [
                'telegram_bot_id' => $bot->id,
                'telegram_chat_id' => $chatId,
            ],
            [
                'telegram_username' => $username,
                'telegram_name' => $name,
                'balance' => 0,
                'held_balance' => 0,
                'is_active' => true,
            ]
        );

        $updates = [];
        if ($username && $username !== $member->telegram_username) {
            $updates['telegram_username'] = $username;
        }
        if ($name && $name !== $member->telegram_name) {
            $updates['telegram_name'] = $name;
        }
        if ($updates !== []) {
            $member->update($updates);
        }

        return $member;
    }

    public function requestOtp(TelegramBot $bot, BotMember $member, OtpService $service): OtpOrder
    {
        $result = $this->requestBulkOtp($bot, $member, $service, 1);
        $orders = array_values($result['orders'] ?? []);

        if ($orders === []) {
            $failed = $result['failed'][0]['message'] ?? 'Gagal membuat pesanan OTP.';
            throw new RuntimeException($failed);
        }

        return $orders[0];
    }

    /**
     * Create 1x - 5x bulk OTP orders under a single batch.
     *
     * @return array{orders: array<int, OtpOrder>, failed: list<array{slot: int, message: string}>}
     */
    public function requestBulkOtp(TelegramBot $bot, BotMember $member, OtpService $service, int $quantity = 1): array
    {
        $quantity = max(1, min(5, $quantity));
        $this->ensureBotApiKey($bot);

        if (! $service->is_enabled || ! $service->is_active) {
            throw ValidationException::withMessages(['service' => 'Layanan tidak aktif.']);
        }

        $sellPrice = $bot->sellPriceFor($service->provider_price);
        $totalPrice = $sellPrice * $quantity;

        if ($member->availableBalance() < $totalPrice) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo tidak cukup untuk '.$quantity.' nomor. Total: Rp '.number_format($totalPrice, 0, ',', '.').', tersedia: '.$member->formattedAvailable(),
            ]);
        }

        $pending = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages(['order' => 'Masih ada OTP pending. Batalkan dulu atau tunggu selesai.']);
        }

        $batchId = $quantity > 1 ? (string) Str::uuid() : null;
        $orders = [];
        $failed = [];
        $stopRemaining = false;
        $stopReason = null;
        $activeProvider = $bot->activeOtpProvider();

        for ($i = 0; $i < $quantity; $i++) {
            $slot = $i + 1;

            if ($stopRemaining) {
                $failed[] = ['slot' => $slot, 'message' => (string) $stopReason];
                continue;
            }

            try {
                $order = DB::transaction(function () use ($bot, $member, $service, $sellPrice, $batchId, $activeProvider) {
                    $item = OtpOrder::create([
                        'batch_id' => $batchId,
                        'telegram_bot_id' => $bot->id,
                        'bot_member_id' => $member->id,
                        'otp_service_id' => $service->id,
                        'provider' => $activeProvider,
                        'idempotency_key' => (string) Str::uuid(),
                        'provider_price' => $service->provider_price,
                        'sell_price' => $sellPrice,
                        'status' => 'pending',
                        'wallet_status' => 'none',
                    ]);

                    $this->wallet->hold($member, $sellPrice, OtpOrder::class, $item->id);
                    $item->update(['wallet_status' => 'held']);

                    try {
                        $client = $this->providerManager->forBot($bot);
                        $data = $client->createOrder($service->provider_service_id, $item->idempotency_key);
                    } catch (\Throwable $e) {
                        $this->wallet->releaseHold($member->fresh(), $sellPrice, OtpOrder::class, $item->id, 'Refund: gagal order provider');
                        $item->update([
                            'status' => 'cancelled',
                            'wallet_status' => 'refunded',
                            'cancelled_at' => now(),
                        ]);
                        throw $e;
                    }

                    $providerOrderId = (string) ($data['id'] ?? '');
                    $providerToken = $data['token'] ?? null;
                    $phone = $data['phone_number'] ?? null;

                    $initStatus = strtolower((string) ($data['status'] ?? 'pending'));
                    $cancelReason = $data['cancel_reason'] ?? $data['reason'] ?? $data['message'] ?? null;
                    $phoneFormatted = $phone ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : '';

                    $isInitCancelled = in_array($initStatus, ['cancelled', 'canceled', 'cancel', 'banned', 'blocked', 'rejected', 'failed', 'expired', 'refunded'], true)
                        || stripos((string) $cancelReason, 'banned') !== false
                        || stripos((string) $cancelReason, 'terblokir') !== false
                        || stripos((string) $cancelReason, 'blocked') !== false
                        || stripos((string) $cancelReason, 'cancel') !== false
                        || stripos((string) $cancelReason, 'dibatalkan') !== false;

                    if ($isInitCancelled) {
                        $this->wallet->releaseHold($member->fresh(), $sellPrice, OtpOrder::class, $item->id, 'Refund: nomor dibatalkan/banned');
                        $item->update([
                            'provider_order_id' => $providerOrderId,
                            'provider_token' => $providerToken,
                            'phone_number' => $phone,
                            'status' => 'cancelled',
                            'wallet_status' => 'refunded',
                            'cancelled_at' => now(),
                            'raw_payload' => $data['raw'] ?? $data,
                        ]);

                        $targetPhone = $phoneFormatted !== '' ? " {$phoneFormatted}" : '';
                        throw new \RuntimeException("Nomor WhatsApp{$targetPhone} terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda.\nSaldo yang tertahan telah dikembalikan.");
                    }

                    $item->update([
                        'provider_order_id' => $providerOrderId,
                        'provider_token' => $providerToken,
                        'phone_number' => $phone,
                        'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : null,
                        'raw_payload' => $data['raw'] ?? $data,
                    ]);

                    return $item->fresh(['otpService', 'botMember']);
                });

                $orders[$i] = $order;
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $failed[] = ['slot' => $slot, 'message' => $message];

                if ($this->isFatalBulkProviderError($message)) {
                    $stopRemaining = true;
                    $stopReason = $message;
                }
            }
        }

        if ($orders === []) {
            $lastErr = ! empty($failed) ? (string) end($failed)['message'] : 'Gagal membuat pesanan OTP.';
            throw new \RuntimeException($lastErr);
        }

        return ['orders' => $orders, 'failed' => $failed];
    }

    public function isFatalBulkProviderError(string $message): bool
    {
        return stripos($message, 'saldo server') !== false
            || stripos($message, 'tidak cukup') !== false
            || stripos($message, 'insufficient') !== false
            || (stripos($message, 'balance') !== false && stripos($message, 'not enough') !== false)
            || stripos($message, 'stok nomor') !== false
            || stripos($message, 'stok habis') !== false
            || stripos($message, 'out of stock') !== false;
    }

    public function refreshOrder(OtpOrder $order, bool $notify = true): OtpOrder
    {
        // 1. Cek apakah waktu order sudah kedaluwarsa (> 20 menit)
        $expiresAt = $order->otpWindowExpiresAt();
        $isTimeExpired = ($expiresAt && $expiresAt->isPast())
            || ($order->created_at && $order->created_at->lt(now()->subMinutes(20)));

        if ($isTimeExpired && ($order->status === 'pending' || ($order->status === 'completed' && ! filled($order->otp_code)))) {
            return $this->refundLocal($order, 'expired', 'Batas waktu pemesanan nomor (20 menit) telah habis.', $notify);
        }

        if (! $order->provider_order_id && ! $order->provider_token) {
            if ($isTimeExpired && ($order->status === 'pending' || ($order->status === 'completed' && ! filled($order->otp_code)))) {
                return $this->refundLocal($order, 'expired', 'Order tanpa ID provider kedaluwarsa.', $notify);
            }
            return $order;
        }

        $canPoll = $order->status === 'pending'
            || ! filled($order->otp_code)
            || $this->isIgnoringProviderCancel((int) $order->id);

        if (! $canPoll) {
            return $order;
        }

        try {
            $bot = $order->telegramBot;
            if (! $bot) {
                return $order;
            }

            $client = $this->providerManager->forOrder($order);
            $data = $client->getOrder((string) $order->provider_order_id, $order->provider_token);
        } catch (\Throwable $e) {
            $errMessage = (string) $e->getMessage();
            Log::warning('refreshOrder failed: '.$errMessage);

            $isNotFoundInProvider = stripos($errMessage, '404') !== false
                || stripos($errMessage, 'not found') !== false
                || stripos($errMessage, 'tidak ditemukan') !== false;

            if (($isNotFoundInProvider || $isTimeExpired) && $order->status === 'pending') {
                return $this->refundLocal(
                    $order,
                    'expired',
                    'Pesanan sudah tidak aktif di server provider. Saldo telah dikembalikan.',
                    $notify
                );
            }

            return $order;
        }

        return $this->applyProviderStatus($order, $data, $notify);
    }

    public function applyProviderStatus(OtpOrder $order, array $data, bool $notify = true): OtpOrder
    {
        $status = strtolower((string) ($data['status'] ?? ''));
        $previousOtp = $order->otp_code;
        $newOtp = $this->extractOtp($data, $order->otp_code);

        $order->update([
            'phone_number' => $data['phone_number'] ?? $order->phone_number,
            'otp_code' => $newOtp,
            'full_text' => $data['full_text'] ?? $data['sms'] ?? $data['sms_text'] ?? $order->full_text,
            'raw_payload' => $data['raw'] ?? $data,
            'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : $order->provider_expire_at,
        ]);

        $order = $order->fresh(['otpService', 'botMember', 'telegramBot']) ?? $order;

        $canComplete = $order->status === 'pending' || $this->isIgnoringProviderCancel((int) $order->id);

        if ($canComplete && filled($newOtp)) {
            return $this->completeOrder($order, $notify);
        }

        if ($notify && $order->status === 'completed' && filled($newOtp) && ($previousOtp === null || $newOtp !== $previousOtp)) {
            $member = $order->botMember;
            $bot = $order->telegramBot;

            if ($bot && $member) {
                $this->telegram->notifyOrderCompleted($bot, $member, $order->fresh());
            }
        }

        $cancelReason = $data['cancel_reason'] ?? $data['reason'] ?? $data['message'] ?? null;
        $isCancelledOrBanned = in_array($status, ['cancelled', 'canceled', 'expired', 'banned', 'blocked', 'rejected', 'refunded', 'failed'], true)
            || stripos((string) $cancelReason, 'banned') !== false
            || stripos((string) $cancelReason, 'terblokir') !== false
            || stripos((string) $cancelReason, 'blocked') !== false;

        if ($isCancelledOrBanned && ($order->status === 'pending' || ($order->status === 'completed' && ! filled($order->otp_code)))) {
            if ($this->isIgnoringProviderCancel((int) $order->id)) {
                return $order->fresh(['otpService', 'botMember', 'telegramBot']) ?? $order;
            }

            $reasonType = (stripos((string) $cancelReason, 'banned') !== false || stripos((string) $cancelReason, 'terblokir') !== false || in_array($status, ['banned', 'blocked'], true))
                ? 'banned'
                : ($status === 'expired' ? 'expired' : 'cancelled');

            return $this->refundLocal($order->fresh(), $reasonType, $cancelReason, $notify);
        }

        return $order->fresh();
    }

    public function completeOrder(OtpOrder $order, bool $notify = true): OtpOrder
    {
        if ($order->status === 'completed') {
            return $order;
        }

        $completed = DB::transaction(function () use ($order) {
            $order = OtpOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'completed') {
                return $order->fresh(['otpService', 'botMember', 'telegramBot']);
            }

            if ($order->wallet_status !== 'charged') {
                $this->wallet->chargeHeld(
                    $order->botMember,
                    $order->sell_price,
                    OtpOrder::class,
                    $order->id,
                    'Potong saldo OTP '.$order->otpService?->name
                );
                $order->wallet_status = 'charged';
            }

            $order->status = 'completed';
            $order->completed_at = now();
            $order->save();

            return $order->fresh(['otpService', 'botMember', 'telegramBot']);
        });

        // Catatan: Jangan panggil doneOrder saat OTP pertama masuk agar sewa tetap aktif untuk fitur Re-OTP (minta ulang OTP hingga 5x)

        if ($notify) {
            $member = $completed->botMember;
            $bot = $completed->telegramBot;
            if ($bot && $member) {
                $this->telegram->notifyOrderCompleted($bot, $member, $completed);
            }
        }

        return $completed;
    }

    public function cancelOrder(OtpOrder $order): OtpOrder
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'Pesanan tidak bisa dibatalkan.']);
        }

        if ($order->provider_order_id || $order->provider_token) {
            try {
                $this->providerManager->forOrder($order)->cancelOrder((string) $order->provider_order_id, $order->provider_token);
            } catch (\Throwable $e) {
                Log::warning('provider cancel failed: '.$e->getMessage());
            }
        }

        return $this->refundLocal($order, 'cancelled');
    }

    protected function refundLocal(OtpOrder $order, string $status, ?string $reason = null, bool $notify = true): OtpOrder
    {
        $refunded = DB::transaction(function () use ($order, $status) {
            $order = OtpOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->wallet_status === 'held') {
                $this->wallet->releaseHold(
                    $order->botMember,
                    $order->sell_price,
                    OtpOrder::class,
                    $order->id,
                    'Refund OTP '.$status
                );
                $order->wallet_status = 'refunded';
            } elseif ($order->wallet_status === 'charged') {
                $this->wallet->credit(
                    $order->botMember,
                    $order->sell_price,
                    OtpOrder::class,
                    $order->id,
                    'Refund OTP '.$status.' (koreksi status)'
                );
                $order->wallet_status = 'refunded';
            }

            $order->status = $status === 'expired' ? 'expired' : 'cancelled';
            $order->cancelled_at = now();
            $order->save();

            return $order->fresh(['otpService', 'botMember', 'telegramBot']);
        });

        if ($notify) {
            $bot = $refunded->telegramBot;
            $member = $refunded->botMember;
            if ($bot && $member) {
                $this->telegram->notifyOrderCancelled($bot, $member, $refunded, $status, $reason);
            }
        }

        return $refunded;
    }

    public function changeNumber(OtpOrder $order): OtpOrder
    {
        if ($order->status !== 'pending' || (! $order->provider_order_id && ! $order->provider_token)) {
            throw ValidationException::withMessages(['order' => 'Tidak bisa ganti nomor pada pesanan ini.']);
        }

        $bot = $order->telegramBot;
        if (! $bot) {
            throw ValidationException::withMessages(['order' => 'Bot tidak ditemukan.']);
        }

        $this->markIgnoringProviderCancel((int) $order->id);
        $oldProviderId = (string) $order->provider_order_id;
        $service = $order->otpService;

        try {
            $client = $this->providerManager->forOrder($order);
            $data = $client->changeNumber((string) $order->provider_order_id, $order->provider_token, $service?->provider_service_id);
        } catch (\Throwable $e) {
            Log::warning('changeNumber API call failed, trying cancel + recreate fallback: '.$e->getMessage());

            try {
                $this->providerManager->forOrder($order)->cancelOrder((string) $order->provider_order_id, $order->provider_token);
            } catch (\Throwable $cancelErr) {
                Log::warning('Fallback provider cancel failed: '.$cancelErr->getMessage());
            }

            if (! $service) {
                throw $e;
            }

            $newKey = (string) Str::uuid();
            $data = $this->providerManager->forBot($bot)->createOrder($service->provider_service_id, $newKey);
        }

        $newOrderId = (string) ($data['id'] ?? $order->provider_order_id);
        $newToken = $data['token'] ?? $order->provider_token;
        $phone = $data['phone_number'] ?? null;

        $initStatus = strtolower((string) ($data['status'] ?? 'pending'));
        $cancelReason = $data['cancel_reason'] ?? $data['reason'] ?? $data['message'] ?? null;
        $phoneFormatted = $phone ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : '';

        $hardFail = in_array($initStatus, ['banned', 'blocked', 'failed', 'expired', 'refunded'], true)
            || stripos((string) $cancelReason, 'banned') !== false
            || stripos((string) $cancelReason, 'terblokir') !== false
            || stripos((string) $cancelReason, 'blocked') !== false;

        $statusSaysCancelled = in_array($initStatus, ['cancelled', 'canceled', 'cancel'], true);
        $isInitCancelled = $hardFail || ($statusSaysCancelled && ! filled($phone));

        if ($isInitCancelled) {
            $this->refundLocal($order, 'banned', $cancelReason);
            $targetPhone = $phoneFormatted !== '' ? " {$phoneFormatted}" : '';
            throw new \RuntimeException("Nomor WhatsApp{$targetPhone} terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda.\nSaldo yang tertahan telah dikembalikan.");
        }

        $order = OtpOrder::query()->whereKey($order->id)->firstOrFail();
        $walletStatus = $order->wallet_status;
        if (in_array($walletStatus, ['refunded', 'none'], true)) {
            $this->wallet->hold(
                $order->botMember,
                $order->sell_price,
                OtpOrder::class,
                $order->id,
                'Hold ulang setelah ganti nomor'
            );
            $walletStatus = 'held';
        }

        $order->update([
            'provider_order_id' => $newOrderId,
            'provider_token' => $newToken,
            'phone_number' => $phone,
            'otp_code' => null,
            'full_text' => null,
            'raw_payload' => $data['raw'] ?? $data,
            'status' => 'pending',
            'cancelled_at' => null,
            'wallet_status' => $walletStatus,
            'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : $order->provider_expire_at,
        ]);

        Log::info('changeNumber ok', [
            'order' => $order->id,
            'old_provider_id' => $oldProviderId,
            'new_provider_id' => $newOrderId,
        ]);

        return $order->fresh(['otpService', 'botMember', 'telegramBot']);
    }

    public function markIgnoringProviderCancel(int $orderId): void
    {
        Cache::put('otp_ignore_cancel:'.$orderId, 1, now()->addSeconds(45));
    }

    public function isIgnoringProviderCancel(int $orderId): bool
    {
        return Cache::has('otp_ignore_cancel:'.$orderId);
    }

    public function reviveHold(OtpOrder $order): OtpOrder
    {
        $order = OtpOrder::query()->whereKey($order->id)->firstOrFail();
        $walletStatus = $order->wallet_status;

        if (in_array($walletStatus, ['refunded', 'none'], true)) {
            $this->wallet->hold(
                $order->botMember,
                $order->sell_price,
                OtpOrder::class,
                $order->id,
                'Hold ulang cek OTP setelah ganti nomor'
            );
            $walletStatus = 'held';
        }

        $order->update([
            'status' => 'pending',
            'cancelled_at' => null,
            'wallet_status' => $walletStatus,
        ]);

        return $order->fresh(['otpService', 'botMember', 'telegramBot']);
    }

    public function resend(OtpOrder $order): void
    {
        $isPending = $order->status === 'pending';
        $isCompletedAndActive = $order->status === 'completed' && $order->canResendOtp();

        if ((! $isPending && ! $isCompletedAndActive) || (! $order->provider_order_id && ! $order->provider_token)) {
            throw ValidationException::withMessages(['order' => 'Order sudah kedaluwarsa atau tidak bisa minta ulang OTP.']);
        }

        $bot = $order->telegramBot;
        if (! $bot) {
            throw ValidationException::withMessages(['order' => 'Bot tidak ditemukan.']);
        }

        $this->providerManager->forOrder($order)->resendOtp((string) $order->provider_order_id, $order->provider_token);
    }

    public function ensureBotApiKey(TelegramBot $bot): void
    {
        if (! $bot->hasOtpConfigured()) {
            throw ValidationException::withMessages([
                'otp_api_key' => 'Isi API Key '.$bot->otpProviderName().' dulu di Konfigurasi Bot.',
            ]);
        }
    }

    protected function extractOtp(array $data, mixed $fallback = null): ?string
    {
        $found = $this->findOtpIn($data);
        if (filled($found)) {
            return $found;
        }

        return filled($fallback) ? trim((string) $fallback) : null;
    }

    protected function findOtpIn(array $data, int $depth = 0): ?string
    {
        if ($depth > 5) {
            return null;
        }

        $skipKeys = ['id', 'provider_order_id', 'service_id', 'phone_number', 'phone', 'price', 'expire_at', 'created_at', 'updated_at', 'balance', 'provider_price', 'sell_price', 'token'];

        // 1. Cek langsung key-key OTP / Code
        foreach (['otp', 'otp_code', 'sms_code', 'verification_code', 'pin', 'passcode', 'kode', 'auth_code'] as $key) {
            if (! isset($data[$key])) {
                continue;
            }
            $value = $data[$key];
            if (is_string($value) || is_numeric($value)) {
                $cleanVal = trim((string) $value);
                if ($cleanVal === '' || str_starts_with($cleanVal, 'wh_rnt_')) {
                    continue;
                }
                // Format 123-456 atau 123 456
                if (preg_match('/^(\d{3})[- ](\d{3})$/', $cleanVal, $m)) {
                    return $m[1].$m[2];
                }
                $digits = preg_replace('/\D+/', '', $cleanVal);
                if (is_string($digits) && preg_match('/^\d{4,8}$/', $digits)) {
                    return $digits;
                }
            }
        }

        // 2. Kumpulkan semua text dari field text/sms/message/content
        $textCandidates = [];
        foreach (['full_text', 'sms', 'sms_text', 'full_sms', 'content', 'message', 'text', 'body', 'last_sms', 'msg', 'messages'] as $tKey) {
            if (! isset($data[$tKey])) {
                continue;
            }
            $val = $data[$tKey];
            if (is_string($val) && trim($val) !== '') {
                $textCandidates[] = trim($val);
            } elseif (is_array($val)) {
                foreach (['text', 'content', 'message', 'body', 'sms', 'code', 'otp', 'val'] as $subKey) {
                    if (isset($val[$subKey]) && is_scalar($val[$subKey])) {
                        $textCandidates[] = trim((string) $val[$subKey]);
                    }
                }
                foreach ($val as $subItem) {
                    if (is_array($subItem)) {
                        foreach (['text', 'content', 'message', 'body', 'sms', 'code', 'otp'] as $subKey) {
                            if (isset($subItem[$subKey]) && is_scalar($subItem[$subKey])) {
                                $textCandidates[] = trim((string) $subItem[$subKey]);
                            }
                        }
                    } elseif (is_string($subItem)) {
                        $textCandidates[] = trim($subItem);
                    }
                }
            }
        }

        foreach ($textCandidates as $text) {
            if ($text === '' || $text === 'Array') {
                continue;
            }
            if (preg_match('/\*(\d{4,8})\*/', $text, $match)) {
                return $match[1];
            }
            if (preg_match('/(?:otp|kode|code|pin|verifikasi|konfirmasi|whatsapp)[^\d]{0,20}(\d{3}[- ]\d{3})/i', $text, $match)) {
                return preg_replace('/\D+/', '', $match[1]);
            }
            if (preg_match('/(?:otp|kode|code|pin|verifikasi|konfirmasi|whatsapp)[^\d]{0,20}(\d{4,8})/i', $text, $match)) {
                return $match[1];
            }
            if (preg_match('/\b(\d{3})[- ](\d{3})\b/', $text, $match)) {
                return $match[1].$match[2];
            }
            if (preg_match('/\b(\d{5,8})\b/', $text, $match)) {
                return $match[1];
            }
            if (preg_match('/\b(\d{4})\b/', $text, $match)) {
                return $match[1];
            }
        }

        // 3. Recursive traversal untuk nested payload
        foreach ($data as $key => $value) {
            if (! is_array($value) || in_array((string) $key, $skipKeys, true)) {
                continue;
            }
            $nested = $this->findOtpIn($value, $depth + 1);
            if (filled($nested)) {
                return $nested;
            }
        }

        return null;
    }
}
