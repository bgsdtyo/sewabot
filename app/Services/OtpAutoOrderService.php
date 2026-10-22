<?php

namespace App\Services;

use App\Models\BotMember;
use App\Models\OtpAutoOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Services\OtpProviderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpAutoOrderService
{
    public function __construct(
        protected OtpOrderService $otpOrderService,
        protected TelegramBotService $telegramBotService
    ) {}

    public function getEffectiveProviderBalance(TelegramBot $bot): ?int
    {
        try {
            $client = app(OtpProviderManager::class)->forBot($bot);
            $data = $client->getBalance();
            $balance = (int) ($data['available'] ?? $data['balance'] ?? 0);
            $bot->update([
                'provider_balance' => $balance,
                'provider_balance_currency' => (string) ($data['currency'] ?? 'IDR'),
                'provider_balance_checked_at' => now(),
            ]);

            return $balance;
        } catch (\Throwable $e) {
            return $bot->provider_balance !== null ? (int) $bot->provider_balance : null;
        }
    }

    public function getActiveForMember(TelegramBot $bot, BotMember $member, int|OtpService|null $service = null): ?OtpAutoOrder
    {
        $serviceId = $service instanceof OtpService ? $service->id : $service;

        $query = OtpAutoOrder::query()
            ->where('telegram_bot_id', $bot->id)
            ->where('bot_member_id', $member->id)
            ->where('status', 'active');

        if ($serviceId) {
            $query->where('otp_service_id', $serviceId);
        }

        return $query->latest('id')->first();
    }

    public function setAutoOrder(TelegramBot $bot, BotMember $member, OtpService $service, int $targetQuantity): OtpAutoOrder
    {
        $targetQuantity = max(1, min(100, $targetQuantity));

        $unitPrice = $bot->sellPriceFor($service->provider_price);
        $totalMemberCost = $unitPrice * $targetQuantity;

        // 1. Validasi saldo member
        if ($member->availableBalance() < $unitPrice) {
            throw ValidationException::withMessages([
                'balance' => 'Saldo Anda tidak mencukupi untuk memesan minimal 1 nomor (Harga: Rp'.number_format($unitPrice, 0, ',', '.').'). Silakan deposit saldo terlebih dahulu.',
            ]);
        }

        $maxByMember = (int) floor($member->availableBalance() / $unitPrice);
        if ($targetQuantity > $maxByMember) {
            $formattedTotalCost = number_format($totalMemberCost, 0, ',', '.');
            throw ValidationException::withMessages([
                'balance' => "⚠️ <b>Saldo Tidak Mencukupi</b>\n\nSaldo Anda (".$member->formattedAvailable().") tidak mencukupi untuk target <b>{$targetQuantity} nomor</b> (Total: Rp{$formattedTotalCost}).\n\nMaksimal target yang dapat Anda pasang saat ini adalah <b>{$maxByMember} nomor</b>.",
            ]);
        }

        // 2. Validasi saldo pusat / modal provider (Dehuy / Kopken / Ninja)
        $modalPerNumber = (int) ($service->provider_price ?? 0);
        if ($modalPerNumber > 0) {
            $providerBalance = $this->getEffectiveProviderBalance($bot);
            if ($providerBalance !== null) {
                $maxByProvider = (int) floor($providerBalance / $modalPerNumber);
                if ($maxByProvider < 1) {
                    throw ValidationException::withMessages([
                        'provider_balance' => '⚠️ <b>Saldo Server Pusat Tidak Mencukupi</b>\n\nSaat ini saldo server pusat sedang kosong atau tidak mencukupi untuk pemesanan nomor baru (Tersedia: Rp'.number_format($providerBalance, 0, ',', '.').', modal: Rp'.number_format($modalPerNumber, 0, ',', '.').'/nomor).\n\nSilakan hubungi admin bot untuk isi ulang saldo server.',
                    ]);
                }

                if ($targetQuantity > $maxByProvider) {
                    $formattedProviderBalance = number_format($providerBalance, 0, ',', '.');
                    $formattedModal = number_format($modalPerNumber, 0, ',', '.');
                    throw ValidationException::withMessages([
                        'provider_balance' => "⚠️ <b>Target Melebihi Kapasitas Server</b>\n\nSaldo pusat (modal) saat ini hanya mencukupi untuk antrean maksimal <b>{$maxByProvider} nomor</b> (Tersedia: Rp{$formattedProviderBalance}, modal: Rp{$formattedModal}/nomor).\n\nSilakan pasang target maksimal <b>{$maxByProvider} nomor</b> atau hubungi admin untuk isi ulang saldo pusat.",
                    ]);
                }
            }
        }

        return OtpAutoOrder::updateOrCreate(
            [
                'telegram_bot_id' => $bot->id,
                'bot_member_id' => $member->id,
                'otp_service_id' => $service->id,
            ],
            [
                'target_quantity' => $targetQuantity,
                'ordered_quantity' => 0,
                'status' => 'active',
                'last_error' => null,
            ]
        );
    }

    public function cancelAutoOrder(TelegramBot $bot, BotMember $member, int|OtpService|null $service = null): bool
    {
        $serviceId = $service instanceof OtpService ? $service->id : $service;

        $query = OtpAutoOrder::query()
            ->where('telegram_bot_id', $bot->id)
            ->where('bot_member_id', $member->id);

        if ($serviceId) {
            $query->where('otp_service_id', $serviceId);
        }

        return (bool) $query->update(['status' => 'cancelled']);
    }

    /**
     * Process active auto orders for a service when stock is detected.
     *
     * @return int Number of successfully placed auto orders.
     */
    public function processAutoOrdersForService(OtpService $service, int $availableStock): int
    {
        if ($availableStock <= 0) {
            return 0;
        }

        $lock = Cache::lock("auto_order_service_{$service->id}", 30);
        if (! $lock->get()) {
            return 0;
        }

        $totalExecuted = 0;

        try {
            $autoOrders = OtpAutoOrder::query()
                ->where('otp_service_id', $service->id)
                ->where('status', 'active')
                ->whereColumn('ordered_quantity', '<', 'target_quantity')
                ->with(['telegramBot', 'botMember', 'otpService'])
                ->orderBy('id', 'asc') // FIFO
                ->get();

            if ($autoOrders->isEmpty()) {
                return 0;
            }

            foreach ($autoOrders as $autoOrder) {
                if ($availableStock <= 0) {
                    break;
                }

                $bot = $autoOrder->telegramBot;
                $member = $autoOrder->botMember;

                if (! $bot || ! $bot->isRunning() || ! $bot->token) {
                    continue;
                }

                if (! $member || ! $member->is_active || ! $member->can_order || ! $member->telegram_chat_id) {
                    continue;
                }

                $unitPrice = $bot->sellPriceFor($service->provider_price);
                $availableBalance = $member->availableBalance();

                if ($availableBalance < $unitPrice) {
                    $autoOrder->update([
                        'status' => 'paused',
                        'last_error' => 'Saldo tidak mencukupi untuk melanjutkan pemesanan',
                    ]);

                    $text = "⚠️ <b>Auto Order Dijeda: Saldo Tidak Cukup</b>\n\n"
                        ."Stok untuk layanan <b>".e($service->name)."</b> saat ini sedang tersedia, tetapi saldo Anda (".$member->formattedAvailable().") tidak mencukupi untuk harga per nomor (Rp".number_format($unitPrice, 0, ',', '.').").\n\n"
                        ."Silakan deposit saldo terlebih dahulu, lalu aktifkan kembali Auto Order.";

                    $this->telegramBotService->sendMessage(
                        $bot,
                        $member->telegram_chat_id,
                        $text,
                        replyMarkup: [
                            'inline_keyboard' => [
                                [['text' => '➕ Deposit Saldo', 'callback_data' => 'deposit']],
                            ],
                        ]
                    );

                    continue;
                }

                $remainingNeeded = $autoOrder->remainingQuantity();
                $canAffordCount = (int) floor($availableBalance / $unitPrice);

                // Batch up to 5 at a time, limited by needed, affordable, and available stock
                $batchQuantity = max(1, min($remainingNeeded, $canAffordCount, 5, $availableStock));

                try {
                    $result = $this->otpOrderService->requestBulkOtp($bot, $member, $service, $batchQuantity);
                    $createdOrders = array_values($result['orders'] ?? []);
                    $countCreated = count($createdOrders);

                    if ($countCreated > 0) {
                        $totalExecuted += $countCreated;
                        $availableStock -= $countCreated;

                        $autoOrder->increment('ordered_quantity', $countCreated);
                        $autoOrder->update([
                            'last_order_at' => now(),
                            'last_error' => null,
                        ]);

                        if ($autoOrder->ordered_quantity >= $autoOrder->target_quantity) {
                            $autoOrder->update(['status' => 'completed']);
                        }

                        // Send summary notification to member
                        $isFinished = $autoOrder->status === 'completed';
                        $statusBadge = $isFinished ? '✅ <b>Target Selesai Tercapai!</b>' : '🟢 <b>Sedang Berjalan...</b>';

                        $lines = [
                            "⚡ <b>AUTO ORDER BERHASIL DIEKSEKUSI!</b> 📦\n",
                            "❏ Layanan: <b>".e($service->name)."</b>",
                            "├  Nomor Baru Didapat: <b>{$countCreated} nomor</b>",
                            "├  Progres Target: <b>{$autoOrder->ordered_quantity} / {$autoOrder->target_quantity} nomor</b>",
                            "└  Status Antrean: {$statusBadge}\n",
                        ];

                        foreach ($createdOrders as $idx => $ord) {
                            $phoneStr = filled($ord->phone_number) ? $this->telegramBotService->formatPhoneNumber($ord->phone_number) : 'Menunggu nomor...';
                            $lines[] = "• Nomor #".($idx + 1).": <code>{$phoneStr}</code>";
                        }

                        $lines[] = "\n<i>Nomor aktif dapat dicek di menu <b>Order Aktif</b> (Masa tunggu 20 menit dari provider).</i>";

                        $this->telegramBotService->sendMessage(
                            $bot,
                            $member->telegram_chat_id,
                            implode("\n", $lines),
                            replyMarkup: [
                                'inline_keyboard' => [
                                    [['text' => '📋 Lihat Order Aktif', 'callback_data' => 'active_orders']],
                                    [['text' => '⚡ Pengaturan Auto Order', 'callback_data' => 'auto_order_menu']],
                                ],
                            ]
                        );

                        // Also send each order's interactive bubble card
                        foreach ($createdOrders as $ord) {
                            $msgId = $this->telegramBotService->sendOrderCard($bot, $ord, $member->telegram_chat_id);
                            if ($msgId) {
                                $this->telegramBotService->rememberOrderMessage($ord, $msgId);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Auto order failed for member #{$member->id}: ".$e->getMessage());
                    $autoOrder->update(['last_error' => $e->getMessage()]);

                    $errMsg = strtolower($e->getMessage());
                    if (
                        str_contains($errMsg, 'stok')
                        || str_contains($errMsg, 'stock')
                        || str_contains($errMsg, 'saldo server')
                        || str_contains($errMsg, 'saldo pusat')
                        || str_contains($errMsg, 'insufficient')
                        || str_contains($errMsg, 'tidak dapat diproses')
                    ) {
                        // Provider stock or provider balance exhausted
                        break;
                    }
                }
            }
        } finally {
            $lock->release();
        }

        return $totalExecuted;
    }
}
