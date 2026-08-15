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

        if (str_starts_with($text, '/start')) {
            $this->sendMessage($bot, $chatId, $this->welcomeText($bot, $member), $this->mainKeyboard());

            return;
        }

        if (str_starts_with($text, '/help') || $this->isButton($text, 'Bantuan')) {
            $this->sendMessage($bot, $chatId, $this->helpText($bot, $member), $this->mainKeyboard());

            return;
        }

        if (str_starts_with($text, '/saldo') || str_starts_with($text, '/balance') || $this->isButton($text, 'Saldo')) {
            $this->sendBalance($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/deposit') || $this->isButton($text, 'Deposit')) {
            $this->sendDepositInfo($bot, $chatId);

            return;
        }

        if (
            str_starts_with($text, '/otp')
            || str_starts_with($text, '/kopken')
            || strcasecmp($text, 'KOPKEN') === 0
            || $this->isButton($text, 'Order OTP')
        ) {
            $this->startKopken($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/batal') || str_starts_with($text, '/cancel') || $this->isButton($text, 'Batalkan')) {
            $this->cancelPending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/ganti') || $this->isButton($text, 'Ganti Nomor')) {
            $this->changePending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/ulang') || str_starts_with($text, '/resend') || $this->isButton($text, 'Ulang OTP')) {
            $this->resendPending($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/status') || $this->isButton($text, 'Status')) {
            $this->statusPending($bot, $member, $chatId);

            return;
        }

        if ($this->isButton($text, 'Riwayat')) {
            $this->showHistory($bot, $member, $chatId);

            return;
        }

        $this->sendMessage(
            $bot,
            $chatId,
            "Perintah tidak dikenali.\n\n".$this->helpText($bot, $member),
            $this->mainKeyboard()
        );
    }

    protected function isButton(string $text, string $label): bool
    {
        return str_contains($text, $label);
    }

    protected function mainKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📱 Order OTP'], ['text' => '💰 Saldo']],
                [['text' => '➕ Deposit'], ['text' => '📦 Status']],
                [['text' => '📋 Riwayat'], ['text' => '🔄 Ulang OTP']],
                [['text' => '🔀 Ganti Nomor'], ['text' => '❌ Batalkan']],
                [['text' => '❓ Bantuan']],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function sendBalance(TelegramBot $bot, $member, int|string $chatId): void
    {
        $text = "<b>Informasi Saldo</b>\n\n"
            .'Total: <b>'.$member->formattedBalance()."</b>\n"
            .'Tersedia: <b>'.$member->formattedAvailable()."</b>\n"
            .'Ditahan: <b>Rp'.number_format($member->held_balance, 0, ',', '.')."</b>\n\n"
            .'Deposit saldo saat ini masih <b>manual</b>. Tekan tombol di bawah untuk hubungi admin.';

        $this->sendMessage($bot, $chatId, $text, null, [
            'inline_keyboard' => [
                [['text' => '➕ Deposit Saldo', 'callback_data' => 'deposit']],
            ],
        ]);
    }

    protected function sendDepositInfo(TelegramBot $bot, int|string $chatId): void
    {
        $note = trim((string) ($bot->deposit_note ?? ''));
        if ($note === '') {
            $note = 'Deposit saldo saat ini dilakukan secara manual. Hubungi admin melalui tombol di bawah, lalu kirim bukti transfer.';
        }

        $text = "<b>Deposit Saldo</b>\n\n".e($note)."\n\n"
            .'Setelah transfer, kirim bukti ke admin agar saldo segera ditambahkan.';

        $row = [];
        if ($wa = $bot->depositWhatsappUrl()) {
            $row[] = ['text' => '💬 WhatsApp', 'url' => $wa];
        }
        if ($tg = $bot->depositTelegramUrl()) {
            $row[] = ['text' => '✈️ Telegram', 'url' => $tg];
        }

        if ($row === []) {
            $text .= "\n\n<i>Kontak admin belum dikonfigurasi. Minta pemilik bot mengisi WhatsApp/Telegram di Konfigurasi Bot.</i>";
            $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard());

            return;
        }

        $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard(), [
            'inline_keyboard' => [$row],
        ]);
    }

    protected function welcomeText(TelegramBot $bot, $member): string
    {
        $service = $this->kopkenService();
        $price = $service ? $bot->formattedSellPriceFor($service->provider_price) : '-';
        $name = e($bot->name);

        return "<b>Selamat datang di {$name}</b>\n\n"
            ."Layanan OTP WhatsApp profesional untuk kebutuhan verifikasi akun Anda.\n\n"
            ."Saldo tersedia: <b>{$member->formattedAvailable()}</b>\n"
            ."Tarif KOPKEN: <b>{$price}</b>\n\n"
            .'Silakan pilih menu di bawah untuk memulai.';
    }

    protected function helpText(TelegramBot $bot, $member): string
    {
        $service = $this->kopkenService();
        $price = $service ? $bot->formattedSellPriceFor($service->provider_price) : '-';

        return "<b>Panduan Penggunaan</b>\n\n"
            ."Saldo tersedia: <b>{$member->formattedAvailable()}</b>\n"
            ."Tarif KOPKEN: <b>{$price}</b>\n\n"
            ."<b>Menu</b>\n"
            ."• Order OTP — minta nomor KOPKEN\n"
            ."• Saldo — cek saldo & hold\n"
            ."• Deposit — hubungi admin (manual)\n"
            ."• Status — pantau order berjalan\n"
            ."• Riwayat — 5 transaksi terakhir\n"
            ."• Ulang OTP — minta ulang kode (gratis)\n"
            ."• Ganti Nomor — ganti nomor pending\n"
            ."• Batalkan — batalkan & refund hold\n"
            ."• Bantuan — panduan ini\n\n"
            ."<b>Perintah teks</b>\n"
            ."/otp · /saldo · /deposit · /status · /ulang · /ganti · /batal\n\n"
            .'Saldo ditahan saat order, dipotong saat OTP masuk, dan di-refund jika dibatalkan.';
    }

    protected function kopkenService(): ?OtpService
    {
        return OtpService::sellable()
            ->where(function ($q) {
                $q->where('slug', 'kopken')->orWhereRaw('UPPER(name) = ?', ['KOPKEN']);
            })
            ->first();
    }

    protected function showHistory(TelegramBot $bot, $member, $chatId): void
    {
        $orders = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->with('otpService')
            ->latest()
            ->limit(5)
            ->get();

        if ($orders->isEmpty()) {
            $this->sendMessage($bot, $chatId, "<b>Riwayat OTP</b>\n\nBelum ada transaksi.", $this->mainKeyboard());

            return;
        }

        $lines = ["<b>Riwayat OTP</b> (5 terakhir)\n"];
        foreach ($orders as $order) {
            $when = $order->created_at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '-';
            $svc = e($order->otpService?->name ?? 'OTP');
            $status = strtoupper($order->status);
            $lines[] = "• {$when} · {$svc} · {$status}";
        }

        $this->sendMessage($bot, $chatId, implode("\n", $lines), $this->mainKeyboard());
    }

    protected function startKopken(TelegramBot $bot, $member, $chatId): void
    {
        $service = $this->kopkenService();

        if (! $service) {
            $this->sendMessage(
                $bot,
                $chatId,
                'Layanan KOPKEN belum tersedia. Pastikan API Key bot sudah dikonfigurasi oleh pemilik bot.',
                $this->mainKeyboard()
            );

            return;
        }

        try {
            $order = app(OtpOrderService::class)->requestOtp($bot, $member, $service);
            $this->sendMessage($bot, $chatId,
                "<b>Order KOPKEN berhasil dibuat</b>\n\n".
                "Nomor: <code>{$order->phone_number}</code>\n".
                'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n".
                "Status: <b>PENDING</b>\n\n".
                "Saldo ditahan hingga OTP masuk.\n".
                'Gunakan Status, Ulang OTP, Ganti Nomor, atau Batalkan bila diperlukan.',
                $this->mainKeyboard()
            );
        } catch (ValidationException $e) {
            $this->sendMessage($bot, $chatId, collect($e->errors())->flatten()->first() ?? 'Gagal membuat order.', $this->mainKeyboard());
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal: '.$e->getMessage(), $this->mainKeyboard());
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
            $this->sendMessage($bot, $chatId, 'Tidak ada order OTP yang sedang berjalan.', $this->mainKeyboard());

            return;
        }

        try {
            app(OtpOrderService::class)->cancelOrder($order);
            $this->sendMessage(
                $bot,
                $chatId,
                'Pesanan dibatalkan. Hold saldo telah di-refund.\nSaldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>',
                $this->mainKeyboard()
            );
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal membatalkan: '.$e->getMessage(), $this->mainKeyboard());
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
            $this->sendMessage($bot, $chatId, 'Tidak ada order OTP yang sedang berjalan.', $this->mainKeyboard());

            return;
        }

        try {
            $order = app(OtpOrderService::class)->changeNumber($order);
            $this->sendMessage(
                $bot,
                $chatId,
                "<b>Nomor diganti</b>\n\nNomor baru: <code>{$order->phone_number}</code>\nStatus: <b>PENDING</b>",
                $this->mainKeyboard()
            );
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal ganti nomor: '.$e->getMessage(), $this->mainKeyboard());
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
            $this->sendMessage($bot, $chatId, 'Tidak ada order OTP yang sedang berjalan.', $this->mainKeyboard());

            return;
        }

        try {
            app(OtpOrderService::class)->resend($order);
            $this->sendMessage($bot, $chatId, 'Permintaan ulang OTP telah dikirim (gratis). Pantau lewat menu Status.', $this->mainKeyboard());
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal mengirim ulang: '.$e->getMessage(), $this->mainKeyboard());
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
            $this->sendMessage($bot, $chatId, "Tidak ada order aktif.\n\nPilih <b>Order OTP</b> untuk memulai.", $this->mainKeyboard());

            return;
        }

        $order = app(OtpOrderService::class)->refreshOrder($order);

        if ($order->status === 'completed') {
            return;
        }

        $this->sendMessage($bot, $chatId,
            "<b>Order Aktif</b>\n\n".
            'Status: <b>'.e($order->status)."</b>\n".
            'Layanan: <b>'.e($order->otpService?->name ?? '-')."</b>\n".
            'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
            'OTP: <b>'.e($order->otp_code ?: '-')."</b>\n".
            'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.').'</b>',
            $this->mainKeyboard()
        );
    }

    protected function handleCallback(TelegramBot $bot, array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $callbackId = $callback['id'] ?? null;

        if ($callbackId) {
            try {
                Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackId,
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (! $chatId) {
            return;
        }

        if ($data === 'deposit') {
            $this->sendDepositInfo($bot, $chatId);
        }
    }

    public function sendMessage(
        TelegramBot $bot,
        int|string $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?array $inlineKeyboard = null
    ): void {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($inlineKeyboard) {
                $payload['reply_markup'] = $inlineKeyboard;
            } elseif ($replyMarkup) {
                $payload['reply_markup'] = $replyMarkup;
            }

            Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());
        }
    }
}
