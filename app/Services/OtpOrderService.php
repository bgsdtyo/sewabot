<?php

namespace App\Services;

use App\Models\BotMember;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OtpOrderService
{
    public function __construct(
        protected OtpProviderClient $provider,
        protected WalletService $wallet,
        protected TelegramBotService $telegram
    ) {}

    public function syncServices(?array $onlyNames = ['KOPKEN'], ?TelegramBot $usingBot = null): int
    {
        $client = $usingBot
            ? $this->provider->forBot($usingBot)
            : $this->provider;

        $items = $client->getServices();
        $count = 0;

        foreach ($items as $item) {
            $name = (string) ($item['name'] ?? '');
            if ($onlyNames && ! in_array(strtoupper($name), array_map('strtoupper', $onlyNames), true)) {
                continue;
            }

            $providerPrice = (int) ($item['price'] ?? 0);

            OtpService::updateOrCreate(
                ['provider_service_id' => (int) $item['id']],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'provider_price' => $providerPrice,
                    'sell_price' => $providerPrice,
                    'duration_seconds' => (int) ($item['duration_seconds'] ?? 1200),
                    'stock' => (int) ($item['stock'] ?? 0),
                    'is_active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    public function getServiceStock(OtpService $service, ?TelegramBot $bot = null): int
    {
        if ($bot && filled($bot->otp_api_key)) {
            try {
                $cacheKey = "bot_{$bot->id}_svc_{$service->provider_service_id}_stock";

                return (int) cache()->remember($cacheKey, 15, function () use ($bot, $service) {
                    $services = $this->provider->forBot($bot)->getServices();
                    foreach ($services as $item) {
                        if ((int) ($item['id'] ?? 0) === (int) $service->provider_service_id) {
                            $stock = (int) ($item['stock'] ?? $item['count'] ?? $item['available'] ?? 0);
                            $service->update(['stock' => $stock]);

                            return $stock;
                        }
                    }

                    return (int) $service->stock;
                });
            } catch (\Throwable $e) {
                Log::warning('Failed fetching live stock from provider: '.$e->getMessage());
            }
        }

        return (int) $service->stock;
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
        $this->ensureBotApiKey($bot);

        if (! $service->is_enabled || ! $service->is_active) {
            throw ValidationException::withMessages(['service' => 'Layanan tidak aktif.']);
        }

        $sellPrice = $bot->sellPriceFor($service->provider_price);

        if ($member->availableBalance() < $sellPrice) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo tidak cukup. Harga Rp'.number_format($sellPrice, 0, ',', '.').', tersedia '.$member->formattedAvailable(),
            ]);
        }

        $pending = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages(['order' => 'Masih ada OTP pending. Batalkan dulu atau tunggu selesai.']);
        }

        return DB::transaction(function () use ($bot, $member, $service, $sellPrice) {
            $order = OtpOrder::create([
                'telegram_bot_id' => $bot->id,
                'bot_member_id' => $member->id,
                'otp_service_id' => $service->id,
                'idempotency_key' => (string) Str::uuid(),
                'provider_price' => $service->provider_price,
                'sell_price' => $sellPrice,
                'status' => 'pending',
                'wallet_status' => 'none',
            ]);

            $this->wallet->hold($member, $sellPrice, OtpOrder::class, $order->id);
            $order->update(['wallet_status' => 'held']);

            try {
                $data = $this->provider->forBot($bot)->createOrder($service->provider_service_id, $order->idempotency_key);
            } catch (\Throwable $e) {
                $this->wallet->releaseHold($member->fresh(), $sellPrice, OtpOrder::class, $order->id, 'Refund: gagal order provider');
                $order->update([
                    'status' => 'cancelled',
                    'wallet_status' => 'refunded',
                    'cancelled_at' => now(),
                ]);
                throw $e;
            }

            $order->update([
                'provider_order_id' => $data['id'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : null,
                'raw_payload' => $data,
            ]);

            return $order->fresh(['otpService', 'botMember']);
        });
    }

    public function refreshOrder(OtpOrder $order): OtpOrder
    {
        if (! $order->provider_order_id || $order->status !== 'pending') {
            return $order;
        }

        try {
            $bot = $order->telegramBot;
            if (! $bot) {
                return $order;
            }
            $data = $this->provider->forBot($bot)->getOrder($order->provider_order_id);
        } catch (\Throwable $e) {
            Log::warning('refreshOrder failed: '.$e->getMessage());

            return $order;
        }

        return $this->applyProviderStatus($order, $data);
    }

    public function applyProviderStatus(OtpOrder $order, array $data): OtpOrder
    {
        $status = strtolower((string) ($data['status'] ?? ''));
        $previousOtp = $order->otp_code;
        $newOtp = $data['otp'] ?? $order->otp_code;

        $order->update([
            'phone_number' => $data['phone_number'] ?? $order->phone_number,
            'otp_code' => $newOtp,
            'full_text' => $data['full_text'] ?? $order->full_text,
            'raw_payload' => $data,
            'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : $order->provider_expire_at,
        ]);

        if ($status === 'completed' && $order->status === 'pending') {
            return $this->completeOrder($order->fresh());
        }

        // If order was already completed, but a new/updated OTP code arrived after resend
        if ($order->status === 'completed' && filled($newOtp) && ($previousOtp === null || $newOtp !== $previousOtp)) {
            $member = $order->botMember;
            $bot = $order->telegramBot;

            if ($bot && $member) {
                $this->telegram->notifyOrderCompleted($bot, $member, $order->fresh());
            }
        }

        if (in_array($status, ['cancelled', 'canceled', 'expired'], true) && $order->status === 'pending') {
            return $this->refundLocal($order->fresh(), $status === 'expired' ? 'expired' : 'cancelled');
        }

        return $order->fresh();
    }

    public function completeOrder(OtpOrder $order): OtpOrder
    {
        if ($order->status === 'completed') {
            return $order;
        }

        $completed = DB::transaction(function () use ($order) {
            $order = OtpOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'completed') {
                return $order->fresh(['otpService', 'botMember', 'telegramBot']);
            }

            if ($order->wallet_status === 'held') {
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

        $member = $completed->botMember;
        $bot = $completed->telegramBot;

        if ($bot && $member) {
            $this->telegram->notifyOrderCompleted($bot, $member, $completed);
        }

        return $completed;
    }

    public function cancelOrder(OtpOrder $order): OtpOrder
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'Pesanan tidak bisa dibatalkan.']);
        }

        if ($order->provider_order_id) {
            try {
                $this->provider->forBot($order->telegramBot)->cancelOrder($order->provider_order_id);
            } catch (\Throwable $e) {
                Log::warning('provider cancel failed: '.$e->getMessage());
            }
        }

        return $this->refundLocal($order, 'cancelled');
    }

    protected function refundLocal(OtpOrder $order, string $status): OtpOrder
    {
        return DB::transaction(function () use ($order, $status) {
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
            }

            $order->status = $status === 'expired' ? 'expired' : 'cancelled';
            $order->cancelled_at = now();
            $order->save();

            return $order->fresh();
        });
    }

    public function changeNumber(OtpOrder $order): OtpOrder
    {
        if ($order->status !== 'pending' || ! $order->provider_order_id) {
            throw ValidationException::withMessages(['order' => 'Tidak bisa ganti nomor pada pesanan ini.']);
        }

        $bot = $order->telegramBot;
        if (! $bot) {
            throw ValidationException::withMessages(['order' => 'Bot tidak ditemukan.']);
        }

        try {
            $data = $this->provider->forBot($bot)->changeNumber($order->provider_order_id);
        } catch (\Throwable $e) {
            Log::warning('changeNumber API call failed, trying cancel + recreate fallback: '.$e->getMessage());

            try {
                $this->provider->forBot($bot)->cancelOrder($order->provider_order_id);
            } catch (\Throwable $cancelErr) {
                Log::warning('Fallback provider cancel failed: '.$cancelErr->getMessage());
            }

            $service = $order->otpService;
            if (! $service) {
                throw $e;
            }

            $newKey = (string) Str::uuid();
            $data = $this->provider->forBot($bot)->createOrder($service->provider_service_id, $newKey);
        }

        $order->update([
            'provider_order_id' => $data['id'] ?? $order->provider_order_id,
            'phone_number' => $data['phone_number'] ?? null,
            'otp_code' => null,
            'full_text' => null,
            'raw_payload' => $data,
            'provider_expire_at' => isset($data['expire_at']) ? now()->setTimestamp((int) $data['expire_at']) : $order->provider_expire_at,
        ]);

        return $order->fresh(['otpService', 'botMember', 'telegramBot']);
    }

    public function resend(OtpOrder $order): void
    {
        $isPending = $order->status === 'pending';
        $isCompletedAndActive = $order->status === 'completed'
            && ($order->provider_expire_at === null || $order->provider_expire_at->isFuture())
            && $order->created_at->isAfter(now()->subMinutes(25));

        if ((! $isPending && ! $isCompletedAndActive) || ! $order->provider_order_id) {
            throw ValidationException::withMessages(['order' => 'Order sudah kedaluwarsa atau tidak bisa minta ulang OTP.']);
        }

        $bot = $order->telegramBot;
        if (! $bot) {
            throw ValidationException::withMessages(['order' => 'Bot tidak ditemukan.']);
        }

        $this->provider->forBot($bot)->resendOtp($order->provider_order_id);
    }

    public function ensureBotApiKey(TelegramBot $bot): void
    {
        if (! filled($bot->otp_api_key)) {
            throw ValidationException::withMessages([
                'otp_api_key' => 'Isi API Key provider dulu di Konfigurasi Bot.',
            ]);
        }
    }
}
