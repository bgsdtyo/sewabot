<?php

namespace App\Services;

use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelegramBotService
{
    public function activate(TelegramBot $bot): TelegramBot
    {
        $bot->syncWebhookUrl();
        $bot->update(['status' => 'active']);

        if ($bot->token) {
            $this->setWebhook($bot->fresh());
        }

        return $bot->fresh();
    }

    public function deactivate(TelegramBot $bot): TelegramBot
    {
        $bot->update(['status' => 'inactive']);

        if ($bot->token) {
            $this->deleteWebhook($bot);
        }

        return $bot->fresh();
    }

    public function expireSubscription($subscription): void
    {
        $subscription->update(['status' => 'expired']);

        if ($subscription->telegramBot) {
            $this->deactivate($subscription->telegramBot);
        }
    }

    public function setWebhook(TelegramBot $bot): bool
    {
        if (! $bot->token) {
            return false;
        }

        $url = $bot->buildWebhookUrl();
        if (! $url) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$bot->token}/setWebhook", [
                'url' => $url,
                'drop_pending_updates' => true,
            ]);

            if ($response->successful() && ($response->json('ok') === true)) {
                $bot->update(['webhook_url' => $url]);

                return true;
            }

            Log::warning('Telegram setWebhook failed', [
                'bot_id' => $bot->id,
                'url' => $url,
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram setWebhook error: '.$e->getMessage());
        }

        return false;
    }

    public function deleteWebhook(TelegramBot $bot): bool
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$bot->token}/deleteWebhook", [
                'drop_pending_updates' => true,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram deleteWebhook error: '.$e->getMessage());

            return false;
        }
    }

    public function handleUpdate(TelegramBot $bot, array $update): void
    {
        if ($bot->status !== 'active' || ! $bot->token) {
            return;
        }

        $message = $update['message'] ?? $update['callback_query']['message'] ?? null;
        $callback = $update['callback_query'] ?? null;

        if ($callback) {
            $this->handleCallback($bot, $callback);

            return;
        }

        if (! $message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? [];
        $text = trim((string) ($message['text'] ?? ''));

        if (! $chatId) {
            return;
        }

        $otp = app(OtpOrderService::class);
        $member = $otp->findOrRegisterMember($bot, $from);

        if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $this->sendMessage($bot, $chatId, $this->helpText($bot, $member));

            return;
        }

        if (str_starts_with($text, '/saldo') || str_starts_with($text, '/balance')) {
            $this->sendMessage($bot, $chatId,
                "Saldo kamu\n".
                'Total: '.$member->formattedBalance()."\n".
                'Tersedia: '.$member->formattedAvailable()."\n".
                'Ditahan: Rp'.number_format($member->held_balance, 0, ',', '.')
            );

            return;
        }

        if (str_starts_with($text, '/otp') || str_starts_with($text, '/kopken') || strcasecmp($text, 'KOPKEN') === 0) {
            $this->startKopken($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/batal') || str_starts_with($text, '/cancel')) {
            $this->cancelPending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/ganti')) {
            $this->changePending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/ulang') || str_starts_with($text, '/resend')) {
            $this->resendPending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/status')) {
            $this->statusPending($bot, $member, $chatId);

            return;
        }

        $this->sendMessage($bot, $chatId, "Perintah tidak dikenal.\n\n".$this->helpText($bot, $member));
    }

    protected function helpText(TelegramBot $bot, $member): string
    {
        $service = OtpService::sellable()
            ->where(function ($q) {
                $q->where('slug', 'kopken')->orWhereRaw('UPPER(name) = ?', ['KOPKEN']);
            })
            ->first();
        $price = $service ? $bot->formattedSellPriceFor($service->provider_price) : '-';

        return "Bot OTP WA — KOPKEN\n\n"
            ."Saldo tersedia: {$member->formattedAvailable()}\n"
            ."Harga KOPKEN: {$price}\n\n"
            ."Perintah:\n"
            ."/saldo — cek saldo\n"
            ."/otp atau /kopken — minta nomor OTP\n"
            ."/status — cek OTP pending\n"
            ."/ulang — minta ulang OTP (gratis)\n"
            ."/ganti — ganti nomor\n"
            ."/batal — batalkan & refund hold\n\n"
            .'Saldo dipotong saat OTP masuk. Jika dibatalkan, hold di-refund.';
    }

    protected function startKopken(TelegramBot $bot, $member, $chatId): void
    {
        $service = OtpService::sellable()
            ->where(function ($q) {
                $q->where('slug', 'kopken')->orWhereRaw('UPPER(name) = ?', ['KOPKEN']);
            })
            ->first();

        if (! $service) {
            $this->sendMessage($bot, $chatId, 'Layanan KOPKEN belum tersedia / API Key bot belum diisi. Owner: isi di Kelola Bot.');

            return;
        }

        try {
            $order = app(OtpOrderService::class)->requestOtp($bot, $member, $service);
            $this->sendMessage($bot, $chatId,
                "Order KOPKEN dibuat\n".
                "Nomor: {$order->phone_number}\n".
                'Harga hold: Rp'.number_format($order->sell_price, 0, ',', '.')."\n".
                "Status: PENDING\n\n".
                "Saldo ditahan dulu. OTP masuk = potong saldo.\n".
                "Pantau otomatis, atau /status\n".
                '/ulang · /ganti · /batal'
            );
        } catch (ValidationException $e) {
            $this->sendMessage($bot, $chatId, collect($e->errors())->flatten()->first() ?? 'Gagal order.');
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal: '.$e->getMessage());
        }
    }

    protected function cancelPending(TelegramBot $bot, $member, $chatId): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $order) {
            $this->sendMessage($bot, $chatId, 'Tidak ada OTP pending.');

            return;
        }

        try {
            app(OtpOrderService::class)->cancelOrder($order);
            $this->sendMessage($bot, $chatId, 'Pesanan dibatalkan. Hold saldo di-refund. Saldo: '.$member->fresh()->formattedAvailable());
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal batal: '.$e->getMessage());
        }
    }

    protected function changePending(TelegramBot $bot, $member, $chatId): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $order) {
            $this->sendMessage($bot, $chatId, 'Tidak ada OTP pending.');

            return;
        }

        try {
            $order = app(OtpOrderService::class)->changeNumber($order);
            $this->sendMessage($bot, $chatId, "Nomor diganti.\nNomor baru: {$order->phone_number}\nStatus: PENDING");
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal ganti: '.$e->getMessage());
        }
    }

    protected function resendPending(TelegramBot $bot, $member, $chatId): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $order) {
            $this->sendMessage($bot, $chatId, 'Tidak ada OTP pending.');

            return;
        }

        try {
            app(OtpOrderService::class)->resend($order);
            $this->sendMessage($bot, $chatId, 'Permintaan ulang OTP dikirim (gratis). Pantau /status');
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal ulang: '.$e->getMessage());
        }
    }

    protected function statusPending(TelegramBot $bot, $member, $chatId): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $order) {
            $this->sendMessage($bot, $chatId, 'Tidak ada OTP pending. Ketik /otp untuk order KOPKEN.');

            return;
        }

        $order = app(OtpOrderService::class)->refreshOrder($order);

        if ($order->status === 'completed') {
            return; // already messaged in completeOrder
        }

        $this->sendMessage($bot, $chatId,
            "Status: {$order->status}\n".
            "Layanan: {$order->otpService?->name}\n".
            "Nomor: {$order->phone_number}\n".
            'OTP: '.($order->otp_code ?: '-')."\n".
            'Hold: Rp'.number_format($order->sell_price, 0, ',', '.')
        );
    }

    protected function handleCallback(TelegramBot $bot, array $callback): void
    {
        // reserved for inline buttons later
    }

    public function sendMessage(TelegramBot $bot, int|string $chatId, string $text): void
    {
        try {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());
        }
    }
}
