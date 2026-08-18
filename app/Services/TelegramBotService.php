<?php

namespace App\Services;

use App\Models\BotMember;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelegramBotService
{
    protected ?TelegramBot $currentBot = null;

    protected ?string $currentFromId = null;

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
        $bot->update(['status' => 'suspended']);

        if ($bot->token) {
            $this->deleteWebhook($bot);
        }

        return $bot->fresh();
    }

    public function expireSubscription($subscription): void
    {
        $subscription->update(['status' => 'expired']);

        if ($subscription->telegramBot) {
            $hasOtherActive = $subscription->telegramBot->subscriptions()
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $hasOtherActive) {
                $this->deactivate($subscription->telegramBot);
            }
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
        if (! $bot->token) {
            return;
        }

        $message = $update['message'] ?? $update['callback_query']['message'] ?? null;
        $callback = $update['callback_query'] ?? null;
        $chatId = $message['chat']['id'] ?? $callback['message']['chat']['id'] ?? null;

        // If bot is suspended or inactive, inform the user and block operations
        if ($bot->status !== 'active') {
            if ($callback && isset($callback['id'])) {
                try {
                    Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                        'callback_query_id' => $callback['id'],
                        'text' => '⚠️ Layanan bot sedang dinonaktifkan (Masa sewa berakhir).',
                        'show_alert' => true,
                    ]);
                } catch (\Throwable $e) {
                    // ignore
                }
            } elseif ($chatId) {
                $this->sendMessage(
                    $bot,
                    $chatId,
                    "⚠️ <b>Layanan Bot Dinonaktifkan</b>\n\nMasa aktif sewa bot ini telah berakhir atau sedang ditangguhkan (SUSPENDED).\n\nSilakan lakukan perpanjangan subscription di website untuk mengaktifkan kembali bot ini."
                );
            }

            return;
        }

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
        $fromId = (string) ($from['id'] ?? $chatId);
        $this->currentBot = $bot;
        $this->currentFromId = $fromId;

        // Wizard admin (setelah klik tombol Cek / Add Deposit)
        if ($bot->isTelegramAdmin($fromId) && $this->handleAdminPendingInput($bot, $chatId, $text, $fromId)) {
            return;
        }

        // Kembali ke menu user
        if ($this->isButton($text, 'Menu User') || str_starts_with(strtolower($text), '/user')) {
            $this->clearAdminPending($bot, $chatId);
            $this->sendMessage(
                $bot,
                $chatId,
                "<b>Menu User</b>\n\nSilakan pilih menu di bawah.",
                $this->mainKeyboard()
            );

            return;
        }

        // —— Admin commands ——
        if ($this->isAdminCommand($text) || $this->isButton($text, 'Rekap Hari Ini') || $this->isButton($text, 'Menu Admin') || $this->isButton($text, 'Cek User') || $this->isButton($text, 'Add Deposit')) {
            if (! $bot->isTelegramAdmin($fromId)) {
                $this->sendMessage(
                    $bot,
                    $chatId,
                    "<b>Akses admin ditolak</b>\n\nTelegram ID kamu: <code>{$fromId}</code>\n\nDaftarkan ID ini di <b>Konfigurasi Bot → Admin Telegram ID</b> pada dashboard web.",
                    $this->mainKeyboard()
                );

                return;
            }

            $this->handleAdminCommand($bot, $chatId, $text, $fromId);

            return;
        }

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

        if (str_starts_with($text, '/akun') || str_starts_with($text, '/account') || $this->isButton($text, 'Akun')) {
            $this->sendAccountInfo($bot, $member, $chatId, $from);

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

        if (str_starts_with($text, '/riwayat') || str_starts_with($text, '/history') || $this->isButton($text, 'Riwayat')) {
            $this->showHistory($bot, $member, $chatId);

            return;
        }

        if (str_starts_with($text, '/aktif') || $this->isButton($text, 'Order Aktif')) {
            $this->showActiveOrders($bot, $member, $chatId);

            return;
        }

        $this->sendMessage(
            $bot,
            $chatId,
            "Perintah tidak dikenali.\n\n".$this->helpText($bot, $member),
            $this->mainKeyboard()
        );
    }

    protected function isAdminCommand(string $text): bool
    {
        $cmd = strtolower(strtok($text, ' ') ?: '');

        return in_array($cmd, ['/admin', '/rekap', '/cek', '/adddeposit', '/menuadmin'], true)
            || str_starts_with(strtolower($text), '/cek@')
            || str_starts_with(strtolower($text), '/admin@')
            || str_starts_with(strtolower($text), '/rekap@')
            || str_starts_with(strtolower($text), '/adddeposit@');
    }

    protected function handleAdminCommand(TelegramBot $bot, int|string $chatId, string $text, string $fromId): void
    {
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        $cmd = strtolower(explode('@', $parts[0] ?? '')[0]);

        if ($cmd === '/admin' || $cmd === '/menuadmin' || $this->isButton($text, 'Menu Admin')) {
            $this->sendAdminMenu($bot, $chatId);

            return;
        }

        if ($cmd === '/rekap' || $this->isButton($text, 'Rekap Hari Ini')) {
            $this->sendAdminDailyRecap($bot, $chatId);

            return;
        }

        if ($cmd === '/cek' || $this->isButton($text, 'Cek User')) {
            $targetId = $parts[1] ?? null;
            if (! $targetId || $this->isButton($text, 'Cek User')) {
                $this->startAdminCekPrompt($bot, $chatId);

                return;
            }
            $this->sendAdminUserCheck($bot, $chatId, $targetId);

            return;
        }

        if ($cmd === '/adddeposit' || $this->isButton($text, 'Add Deposit')) {
            if ($this->isButton($text, 'Add Deposit')) {
                $this->startAdminDepositPrompt($bot, $chatId);

                return;
            }

            $targetId = $parts[1] ?? null;
            $amountRaw = $parts[2] ?? null;
            if (! $targetId || $amountRaw === null) {
                $this->startAdminDepositPrompt($bot, $chatId);

                return;
            }

            $amount = (int) preg_replace('/\D+/', '', $amountRaw);
            $this->adminAddDeposit($bot, $chatId, $targetId, $amount, $fromId);

            return;
        }

        $this->sendAdminMenu($bot, $chatId);
    }

    protected function adminKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📊 Rekap Hari Ini'], ['text' => '🔍 Cek User']],
                [['text' => '➕ Add Deposit'], ['text' => '🛠 Menu Admin']],
                [['text' => '⬅️ Menu User']],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function adminInlineMenu(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Rekap Hari Ini', 'callback_data' => 'admin_rekap'],
                ],
                [
                    ['text' => '🔍 Cek User', 'callback_data' => 'admin_cek'],
                    ['text' => '➕ Add Deposit', 'callback_data' => 'admin_adddeposit'],
                ],
                [
                    ['text' => '⬅️ Menu User', 'callback_data' => 'admin_user_menu'],
                ],
            ],
        ];
    }

    protected function adminPendingKey(TelegramBot $bot, int|string $chatId): string
    {
        return 'tg_admin_pending:'.$bot->id.':'.$chatId;
    }

    protected function setAdminPending(TelegramBot $bot, int|string $chatId, array $state): void
    {
        Cache::put($this->adminPendingKey($bot, $chatId), $state, now()->addMinutes(15));
    }

    protected function clearAdminPending(TelegramBot $bot, int|string $chatId): void
    {
        Cache::forget($this->adminPendingKey($bot, $chatId));
    }

    protected function getAdminPending(TelegramBot $bot, int|string $chatId): ?array
    {
        $state = Cache::get($this->adminPendingKey($bot, $chatId));

        return is_array($state) ? $state : null;
    }

    protected function startAdminCekPrompt(TelegramBot $bot, int|string $chatId): void
    {
        $this->setAdminPending($bot, $chatId, ['action' => 'cek']);
        $this->sendMessage(
            $bot,
            $chatId,
            "<b>Cek User</b>\n\nKirim <b>Telegram ID</b> member sekarang.\nContoh: <code>123456789</code>\n\nKetik /bataladmin untuk membatalkan.",
            $this->adminKeyboard()
        );
    }

    protected function startAdminDepositPrompt(TelegramBot $bot, int|string $chatId): void
    {
        $this->setAdminPending($bot, $chatId, ['action' => 'deposit_id']);
        $this->sendMessage(
            $bot,
            $chatId,
            "<b>Add Deposit</b>\n\nKirim <b>Telegram ID</b> member sekarang.\nContoh: <code>123456789</code>\n\nKetik /bataladmin untuk membatalkan.",
            $this->adminKeyboard()
        );
    }

    /**
     * @return bool true jika input sudah ditangani wizard admin
     */
    protected function handleAdminPendingInput(TelegramBot $bot, int|string $chatId, string $text, string $fromId): bool
    {
        if (str_starts_with(strtolower($text), '/bataladmin')) {
            if ($this->getAdminPending($bot, $chatId)) {
                $this->clearAdminPending($bot, $chatId);
                $this->sendMessage($bot, $chatId, 'Wizard admin dibatalkan.', $this->adminKeyboard());

                return true;
            }

            return false;
        }

        $state = $this->getAdminPending($bot, $chatId);
        if (! $state) {
            return false;
        }

        // Jangan makan command admin lain / pindah menu
        if (
            $this->isAdminCommand($text)
            || $this->isButton($text, 'Menu Admin')
            || $this->isButton($text, 'Rekap Hari Ini')
            || $this->isButton($text, 'Menu User')
        ) {
            $this->clearAdminPending($bot, $chatId);

            return false;
        }

        $action = $state['action'] ?? null;

        if ($action === 'cek') {
            $id = preg_replace('/\D+/', '', $text) ?: '';
            if ($id === '') {
                $this->sendMessage($bot, $chatId, 'ID tidak valid. Kirim angka Telegram ID, atau /bataladmin.', $this->adminKeyboard());

                return true;
            }
            $this->clearAdminPending($bot, $chatId);
            $this->sendAdminUserCheck($bot, $chatId, $id);

            return true;
        }

        if ($action === 'deposit_id') {
            $id = preg_replace('/\D+/', '', $text) ?: '';
            if ($id === '') {
                $this->sendMessage($bot, $chatId, 'ID tidak valid. Kirim angka Telegram ID, atau /bataladmin.', $this->adminKeyboard());

                return true;
            }
            $this->setAdminPending($bot, $chatId, ['action' => 'deposit_amount', 'target_id' => $id]);
            $this->sendMessage(
                $bot,
                $chatId,
                "ID member: <code>{$id}</code>\n\nSekarang kirim <b>nominal</b> deposit.\nContoh: <code>50000</code>",
                $this->adminKeyboard()
            );

            return true;
        }

        if ($action === 'deposit_amount') {
            $amount = (int) preg_replace('/\D+/', '', $text);
            $targetId = (string) ($state['target_id'] ?? '');
            if ($amount < 100 || $targetId === '') {
                $this->sendMessage($bot, $chatId, 'Nominal minimal Rp100. Kirim ulang nominal, atau /bataladmin.', $this->adminKeyboard());

                return true;
            }
            $this->clearAdminPending($bot, $chatId);
            $this->adminAddDeposit($bot, $chatId, $targetId, $amount, $fromId);

            return true;
        }

        $this->clearAdminPending($bot, $chatId);

        return false;
    }

    protected function sendAdminMenu(TelegramBot $bot, int|string $chatId): void
    {
        $text = "<b>Panel Admin Bot</b>\n\n"
            ."Mode admin aktif.\n\n"
            ."• Rekap Hari Ini\n"
            ."• Cek User\n"
            ."• Add Deposit\n\n"
            ."Kembali ke menu member: <b>⬅️ Menu User</b>\n\n"
            ."Atau ketik:\n"
            ."<code>/cek 123456789</code>\n"
            .'<code>/adddeposit 123456789 50000</code>';

        $this->sendMessage($bot, $chatId, $text, $this->adminKeyboard());
    }

    protected function sendAdminDailyRecap(TelegramBot $bot, int|string $chatId): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::now($tz)->toDateString();

        $wallet = WalletTransaction::query()
            ->where('telegram_bot_id', $bot->id)
            ->whereDate('created_at', $today);

        $topup = (int) (clone $wallet)->where('type', 'topup')->sum('amount');
        $charge = abs((int) (clone $wallet)->where('type', 'charge')->sum('amount'));
        $refund = (int) (clone $wallet)->where('type', 'refund')->sum('amount');
        $txCount = (clone $wallet)->count();

        $otpBase = OtpOrder::query()
            ->where('telegram_bot_id', $bot->id)
            ->whereDate('created_at', $today);

        $otpTotal = (clone $otpBase)->count();
        $otpDone = (clone $otpBase)->where('status', 'completed')->count();
        $otpPending = (clone $otpBase)->where('status', 'pending')->count();
        $otpCancel = (clone $otpBase)->whereIn('status', ['cancelled', 'expired'])->count();
        $otpRevenue = (int) (clone $otpBase)->where('status', 'completed')->sum('sell_price');

        $membersNew = BotMember::query()
            ->where('telegram_bot_id', $bot->id)
            ->whereDate('created_at', $today)
            ->count();

        $dateLabel = Carbon::now($tz)->translatedFormat('d M Y');

        $text = "<b>Rekap Hari Ini</b> — {$dateLabel}\n\n"
            ."<b>Wallet</b>\n"
            .'Deposit/topup: <b>Rp'.number_format((int) $topup, 0, ',', '.')."</b>\n"
            .'Charge OTP: <b>Rp'.number_format($charge, 0, ',', '.')."</b>\n"
            .'Refund: <b>Rp'.number_format((int) $refund, 0, ',', '.')."</b>\n"
            ."Transaksi wallet: <b>{$txCount}</b>\n\n"
            ."<b>OTP</b>\n"
            ."Order: <b>{$otpTotal}</b> · Selesai: <b>{$otpDone}</b>\n"
            ."Pending: <b>{$otpPending}</b> · Batal/expired: <b>{$otpCancel}</b>\n"
            .'Omzet OTP selesai: <b>Rp'.number_format($otpRevenue, 0, ',', '.')."</b>\n\n"
            ."Member baru hari ini: <b>{$membersNew}</b>";

        $this->sendMessage($bot, $chatId, $text, $this->adminKeyboard());
    }

    protected function sendAdminUserCheck(TelegramBot $bot, int|string $chatId, string $targetId): void
    {
        $id = preg_replace('/\D+/', '', $targetId) ?: '';
        $target = BotMember::query()
            ->where('telegram_bot_id', $bot->id)
            ->where('telegram_chat_id', $id)
            ->first();

        if (! $target) {
            $this->sendMessage($bot, $chatId, "Member tidak ditemukan.\nID: <code>{$id}</code>", $this->adminKeyboard());

            return;
        }

        $orders = OtpOrder::query()->where('bot_member_id', $target->id)->count();
        $ordersToday = OtpOrder::query()
            ->where('bot_member_id', $target->id)
            ->whereDate('created_at', Carbon::today(config('app.timezone', 'Asia/Jakarta')))
            ->count();
        $tz = config('app.timezone', 'Asia/Jakarta');
        $joined = $target->created_at?->timezone($tz)->format('d-m-Y H:i') ?? '-';
        $username = $target->telegram_username ? '@'.ltrim($target->telegram_username, '@') : '-';

        $text = "<b>Data Member</b> 👤\n\n"
            ."❏ User: <b>".e($target->telegram_name ?: '-')."</b>\n"
            ."├  Username: <b>".e($username)."</b>\n"
            ."├  Telegram ID: <code>".e((string) $target->telegram_chat_id)."</code>\n"
            ."├  Status: <b>".($target->is_active ? 'Aktif' : 'Nonaktif')."</b>\n"
            ."├  Terdaftar: {$joined}\n"
            ."├  Saldo Total: <b>".$target->formattedBalance()."</b>\n"
            ."├  Saldo Tersedia: <b>".$target->formattedAvailable()."</b>\n"
            ."├  Saldo Ditahan: <b>Rp".number_format($target->held_balance, 0, ',', '.')."</b>\n"
            ."└  Order OTP: <b>{$orders}</b> (Hari ini: <b>{$ordersToday}</b>)";

        $this->sendMessage($bot, $chatId, $text, $this->adminKeyboard());
    }

    protected function adminAddDeposit(
        TelegramBot $bot,
        int|string $chatId,
        string $targetId,
        int $amount,
        string $adminId
    ): void {
        if ($amount < 100) {
            $this->sendMessage($bot, $chatId, 'Nominal minimal Rp100.', $this->adminKeyboard());

            return;
        }

        $id = preg_replace('/\D+/', '', $targetId) ?: '';
        $target = BotMember::query()
            ->where('telegram_bot_id', $bot->id)
            ->where('telegram_chat_id', $id)
            ->first();

        if (! $target) {
            $this->sendMessage($bot, $chatId, "Member tidak ditemukan.\nID: <code>{$id}</code>", $this->adminKeyboard());

            return;
        }

        try {
            $updated = app(WalletService::class)->topup(
                $target,
                $amount,
                'Deposit admin TG #'.$adminId
            );

            $this->sendMessage(
                $bot,
                $chatId,
                "<b>Deposit berhasil</b>\n\n"
                .'Member: <b>'.e($updated->displayName())."</b>\n"
                .'ID: <code>'.e((string) $updated->telegram_chat_id)."</code>\n"
                .'Ditambah: <b>Rp'.number_format($amount, 0, ',', '.')."</b>\n"
                .'Saldo sekarang: <b>'.$updated->formattedBalance().'</b>',
                $this->adminKeyboard()
            );

            $this->sendMessage(
                $bot,
                $updated->telegram_chat_id,
                "<b>Saldo ditambahkan</b>\n\n"
                .'Nominal: <b>Rp'.number_format($amount, 0, ',', '.')."</b>\n"
                .'Saldo tersedia: <b>'.$updated->formattedAvailable().'</b>'
            );
        } catch (ValidationException $e) {
            $this->sendMessage($bot, $chatId, collect($e->errors())->flatten()->first() ?? 'Gagal deposit.', $this->adminKeyboard());
        } catch (\Throwable $e) {
            $this->sendMessage($bot, $chatId, 'Gagal: '.$e->getMessage(), $this->adminKeyboard());
        }
    }

    protected function isButton(string $text, string $label): bool
    {
        return str_contains($text, $label);
    }

    protected function mainKeyboard(): array
    {
        $rows = [
            [['text' => '📱 Order OTP'], ['text' => '💰 Saldo']],
            [['text' => '➕ Deposit'], ['text' => '👤 Akun']],
            [['text' => '📦 Order Aktif'], ['text' => '📋 Riwayat']],
            [['text' => '❓ Bantuan']],
        ];

        if ($this->currentBot && $this->currentFromId && $this->currentBot->isTelegramAdmin($this->currentFromId)) {
            $rows[] = [['text' => '🛠 Menu Admin']];
        }

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function sendAccountInfo(TelegramBot $bot, $member, int|string $chatId, array $from = []): void
    {
        $member->refresh();

        $name = $member->telegram_name
            ?: trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''))
            ?: '-';
        $username = $member->telegram_username
            ? '@'.ltrim($member->telegram_username, '@')
            : (($from['username'] ?? null) ? '@'.$from['username'] : '-');
        $telegramId = $member->telegram_chat_id ?: ($from['id'] ?? '-');
        $tz = config('app.timezone', 'Asia/Jakarta');
        $joined = $member->created_at
            ? $member->created_at->timezone($tz)->format('d-m-Y H:i')
            : '-';
        $status = $member->is_active ? 'Aktif' : 'Nonaktif';
        $ordersCount = OtpOrder::query()->where('bot_member_id', $member->id)->count();

        $text = "<b>Informasi Akun</b> 👤\n\n"
            ."❏ User: <b>".e($name)."</b>\n"
            ."├  Username: <b>".e($username)."</b>\n"
            ."├  Telegram ID: <code>".e((string) $telegramId)."</code>\n"
            ."├  Status: <b>".e($status)."</b>\n"
            ."├  Terdaftar: {$joined}\n"
            ."├  Saldo Tersedia: <b>".$member->formattedAvailable()."</b>\n"
            ."├  Saldo Ditahan: <b>Rp".number_format($member->held_balance, 0, ',', '.')."</b>\n"
            ."└  Total Order: <b>{$ordersCount} Transaksi</b>";

        $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard());
    }

    protected function sendBalance(TelegramBot $bot, $member, int|string $chatId): void
    {
        $member->refresh();
        $name = $member->telegram_name ?: ($member->telegram_username ? '@'.ltrim($member->telegram_username, '@') : 'Member');
        $telegramId = $member->telegram_chat_id;

        $text = "<b>Informasi Saldo</b> 💳\n\n"
            ."❏ User: <b>".e($name)."</b>\n"
            ."├  Telegram ID: <code>".e((string) $telegramId)."</code>\n"
            ."├  Saldo Total: <b>".$member->formattedBalance()."</b>\n"
            ."├  Saldo Tersedia: <b>".$member->formattedAvailable()."</b>\n"
            ."└  Saldo Ditahan: <b>Rp".number_format($member->held_balance, 0, ',', '.')."</b>\n\n"
            ."<i>Deposit saldo saat ini diproses secara manual. Tekan tombol di bawah untuk deposit.</i>";

        $this->sendMessage($bot, $chatId, $text, null, [
            'inline_keyboard' => [
                [['text' => '➕ Deposit Saldo', 'callback_data' => 'deposit']],
            ],
        ]);
    }

    protected function sendDepositInfo(TelegramBot $bot, int|string $chatId): void
    {
        $tgUsername = trim((string) $bot->deposit_telegram);
        if ($tgUsername !== '') {
            $adminHandle = str_starts_with($tgUsername, '@') ? $tgUsername : '@'.ltrim($tgUsername, '@');
        } else {
            $adminHandle = 'Admin';
        }

        $text = "<b>Deposit Saldo</b> 📥\n\n"
            ."Silakan hubungi admin <b>".e($adminHandle)."</b> atau klik tombol di bawah untuk info pembayaran & konfirmasi deposit saldo.";

        $row = [];
        if ($tg = $bot->depositTelegramUrl()) {
            $row[] = ['text' => '✈️ Hubungi Admin Telegram', 'url' => $tg];
        }
        if ($wa = $bot->depositWhatsappUrl()) {
            $row[] = ['text' => '💬 Hubungi WhatsApp', 'url' => $wa];
        }

        if ($row === []) {
            $text .= "\n\n<i>Kontak admin belum dikonfigurasi di dashboard web.</i>";
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
            ."<b>Menu Utama</b>\n"
            ."• 📱 Order OTP — Pesan nomor baru\n"
            ."• 💰 Saldo — Cek saldo & hold\n"
            ."• ➕ Deposit — Informasi rekening & deposit\n"
            ."• 👤 Akun — Data profil & total order\n"
            ."• 📋 Riwayat — 5 transaksi terakhir\n"
            ."• ❓ Bantuan — Panduan penggunaan ini\n\n"
            ."<i>Aksi pesanan (Ganti Nomor, Ulang OTP, Batalkan) dapat langsung dilakukan melalui tombol pada bubble transaksi masing-masing.</i>";
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
            $this->sendMessage($bot, $chatId, "<b>Riwayat Transaksi</b> 💰\n\nBelum ada riwayat transaksi.", $this->mainKeyboard());

            return;
        }

        $cards = [];
        foreach ($orders as $order) {
            $cards[] = $this->formatOrderCard($order);
        }

        $text = "<b>Riwayat Transaksi</b> 💰\n\n".implode("\n\n", $cards);

        $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard());
    }

    protected function showActiveOrders(TelegramBot $bot, $member, int|string $chatId): void
    {
        $orders = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->with('otpService')
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            $this->sendMessage(
                $bot,
                $chatId,
                "⚠️ <b>Tidak Ada Order Aktif</b>\n\nSaat ini Anda tidak memiliki pesanan pending yang sedang aktif.",
                $this->mainKeyboard()
            );

            return;
        }

        $count = $orders->count();
        $text = "📦 <b>Order Aktif</b> — {$count} order aktif\n\n"
            .'Pilih order untuk melihat nomornya:';

        $buttons = [];
        foreach ($orders as $order) {
            $phone = $this->formatPhoneNumber($order->phone_number);
            $phoneLabel = $phone !== '' ? $phone : 'Menunggu nomor...';
            $serviceName = e($order->otpService?->name ?? 'Kopken');
            $buttons[] = [['text' => "{$phoneLabel} | {$serviceName}", 'callback_data' => 'otp_view_order:'.$order->id]];
        }

        $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard(), ['inline_keyboard' => $buttons]);
    }

    protected function showOrderDetail(TelegramBot $bot, $member, int|string $chatId, int $orderId, ?int $editMessageId = null): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->whereKey($orderId)
            ->with('otpService')
            ->first();

        if (! $order) {
            $this->replyOrSend($bot, $chatId, $editMessageId, 'Order tidak ditemukan.', removeInlineKeyboard: true);

            return;
        }

        // Refresh dari provider jika masih pending
        if ($order->status === 'pending' && $order->provider_order_id) {
            try {
                $order = app(OtpOrderService::class)->refreshOrder($order);
            } catch (\Throwable) {}
        }

        $service = e($order->otpService?->name ?? 'Kopken');

        if ($order->status === 'completed') {
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} — OTP MASUK 🎉",
                footer: 'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>',
                statusOverride: 'Berhasil'
            );
            $keyboard = $this->completedOrderKeyboard($order);
        } elseif ($order->status === 'pending') {
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} 📲",
                footer: 'OTP akan masuk otomatis ke bubble pemesanan.'
            );
            $keyboard = $this->orderActionKeyboard($order);
        } else {
            $text = $this->formatOrderCard($order, title: "Order {$service}");
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '📱 Pesan Lagi', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)]],
                ],
            ];
        }

        $this->replyOrSend($bot, $chatId, $editMessageId, $text, inlineKeyboard: $keyboard);
    }

    protected function startKopken(TelegramBot $bot, $member, $chatId): void
    {
        $this->startOrderForService($bot, $member, $chatId);
    }

    protected function startOrderForService(
        TelegramBot $bot,
        $member,
        int|string $chatId,
        ?int $serviceId = null,
        ?int $editMessageId = null,
        bool $forceFreshStock = false,
        ?string $callbackId = null,
        int $quantity = 1
    ): void {
        $quantity = max(1, min(5, $quantity));
        $service = ($serviceId ? OtpService::sellable()->whereKey($serviceId)->first() : null) ?? $this->kopkenService();

        if (! $service) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                'Layanan OTP belum tersedia. Pastikan API Key bot sudah dikonfigurasi oleh pemilik bot.',
                $this->mainKeyboard()
            );

            return;
        }

        $pending = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        // Auto-refresh dari provider: pastikan order yg sudah selesai di provider
        // tidak terus memblokir user akibat status stale di database kita.
        if ($pending) {
            $otpRefreshService = app(OtpOrderService::class);
            try {
                if ($pending->isPartOfBatch()) {
                    foreach ($pending->getBatchOrders() as $bOrder) {
                        if ($bOrder->status === 'pending' && $bOrder->provider_order_id) {
                            $otpRefreshService->refreshOrder($bOrder);
                        }
                    }
                } elseif ($pending->provider_order_id) {
                    $otpRefreshService->refreshOrder($pending);
                }
            } catch (\Throwable) {}

            // Re-query setelah refresh — jika sudah selesai, lanjut ke order baru
            $pending = OtpOrder::query()
                ->where('bot_member_id', $member->id)
                ->where('status', 'pending')
                ->latest()
                ->first();
        }

        if ($pending) {
            if ($pending->isPartOfBatch()) {
                $batchOrders = collect($pending->getBatchOrders());
                $numIcons = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣'];

                $lines = ["<b>Masih Ada Order Aktif ⚠️</b>\n"];
                foreach ($batchOrders as $idx => $bOrder) {
                    $icon = $numIcons[$idx] ?? ('#'.($idx + 1));
                    $phone = $this->formatPhoneNumber($bOrder->phone_number);
                    $phoneStr = $phone !== '' ? '<code>'.e($phone).'</code>' : '<i>Menunggu nomor...</i>';
                    $lines[] = "{$icon} {$phoneStr} — Status: Pending";
                }
                $lines[] = "\n<i>Selesaikan / batalkan order ini dulu sebelum membuat order baru.</i>";
                $text = implode("\n", $lines);

                // Tombol per order — tidak ada lagi "Batalkan Semua" / "Cek Status Semua"
                $buttons = [];
                foreach ($batchOrders as $idx => $bOrder) {
                    $slotNum = $idx + 1;
                    $buttons[] = [
                        ['text' => "🔀 Ganti #{$slotNum}", 'callback_data' => 'otp_change:'.$bOrder->id],
                        ['text' => "❌ Batalkan #{$slotNum}", 'callback_data' => 'otp_cancel:'.$bOrder->id],
                    ];
                }
                $kb = ['inline_keyboard' => $buttons];
            } else {
                $text = $this->formatOrderCard(
                    $pending,
                    title: 'Masih Ada Order Aktif ⚠️',
                    footer: 'Selesaikan / batalkan order ini dulu sebelum membuat order baru.'
                );
                $kb = $this->orderActionKeyboard($pending);
            }

            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                $text,
                inlineKeyboard: $kb
            );

            return;
        }

        $unitPrice = $bot->sellPriceFor($service->provider_price);
        $totalPrice = $unitPrice * $quantity;
        $available = $member->availableBalance();

        if ($available < $unitPrice) {
            $formattedPrice = 'Rp '.number_format($unitPrice, 0, ',', '.');
            $text = "<b>Saldo Tidak Cukup</b> ⚠️\n\n"
                ."❏ Layanan: <b>".e($service->name)."</b>\n"
                ."├  Harga: <b>{$formattedPrice}</b>\n"
                ."├  Saldo Tersedia: <b>".$member->formattedAvailable()."</b>\n"
                ."└  Status: <b>Perlu Deposit</b>\n\n"
                ."<i>Silakan deposit saldo terlebih dahulu melalui menu Deposit.</i>";

            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                $text,
                $this->mainKeyboard(),
                inlineKeyboard: [
                    'inline_keyboard' => [
                        [['text' => '➕ Deposit Saldo', 'callback_data' => 'deposit']],
                    ],
                ]
            );

            return;
        }

        $otpOrderService = app(OtpOrderService::class);
        $stock = $otpOrderService->getServiceStock($service, $bot, $forceFreshStock);
        $stockFormatted = $stock > 0 ? number_format($stock, 0, ',', '.').' nomor' : '0 (Habis)';
        $formattedUnitPrice = 'Rp '.number_format($unitPrice, 0, ',', '.');
        $formattedTotalHold = 'Rp '.number_format($totalPrice, 0, ',', '.');
        
        if ($available >= $totalPrice) {
            $formattedAfterHold = 'Rp '.number_format($available - $totalPrice, 0, ',', '.');
        } else {
            $shortage = $totalPrice - $available;
            $formattedAfterHold = '⚠️ Kurang Rp '.number_format($shortage, 0, ',', '.');
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $checkTime = now()->timezone($tz)->format('H:i:s');

        if ($callbackId) {
            try {
                if ($available < $totalPrice) {
                    $shortage = $totalPrice - $available;
                    $toastText = "⚠️ Saldo kurang Rp ".number_format($shortage, 0, ',', '.')." untuk {$quantity}x nomor";
                } elseif ($stock > 0) {
                    $toastText = "Pilihan {$quantity}x nomor dipilih (Total: {$formattedTotalHold})";
                } else {
                    $toastText = "⚠️ Stok masih kosong (0 nomor) pada {$checkTime} WIB";
                }

                Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackId,
                    'text' => $toastText,
                    'show_alert' => false,
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($stock <= 0) {
            $text = "<b>Stok Nomor Habis</b> ⚠️\n\n"
                ."❏ Layanan: <b>".e($service->name)."</b>\n"
                ."├  Harga: <b>{$formattedUnitPrice}</b>\n"
                ."├  Stok Nomor: <b>0 (Habis)</b>\n"
                ."├  Cek Terakhir: <b>{$checkTime} WIB</b>\n"
                ."├  Saldo Tersedia: <b>".$member->formattedAvailable()."</b>\n"
                ."└  Status: <b>Stok Kosong</b>\n\n"
                ."<i>Stok nomor untuk layanan ini saat ini sedang habis. Silakan klik Cek Stok Lagi untuk cek ulang atau coba lagi nanti.</i>";

            $buttons = [
                [
                    ['text' => '🔄 Cek Stok Lagi', 'callback_data' => 'otp_check_stock:'.$service->id],
                    ['text' => '❌ Tutup', 'callback_data' => 'otp_preview_cancel'],
                ],
            ];
        } else {
            $text = "<b>Konfirmasi Pesanan</b> 📲\n\n"
                ."❏ Layanan: <b>".e($service->name)."</b>\n"
                ."├  Harga: <b>{$formattedUnitPrice} × {$quantity}</b>\n"
                ."├  Total Hold: <b>{$formattedTotalHold}</b>\n"
                ."├  Waktu: <b>20 menit</b>\n"
                ."├  Stok Nomor: <b>{$stockFormatted}</b>\n"
                ."├  Saldo Tersedia: <b>".$member->formattedAvailable()."</b>\n"
                ."└  Setelah Hold: <b>{$formattedAfterHold}</b>\n\n"
                ."💡 <i>Saldo akan otomatis terpotong setelah OTP berhasil diterima, jika terjadi pembatalan, saldo otomatis dikembalikan atau 'refund'</i>";

            // Row 1: Quantity selector (1x - 5x)
            $qtyRow = [];
            for ($q = 1; $q <= 5; $q++) {
                $label = $q === $quantity ? "✅ {$q}x" : "{$q}x";
                $qtyRow[] = ['text' => $label, 'callback_data' => "otp_qty:{$service->id}:{$q}"];
            }

            // Row 2: Actions
            $actionRow = [
                ['text' => '❌ Batal', 'callback_data' => 'otp_preview_cancel'],
                ['text' => '⚡ Konfirmasi', 'callback_data' => "otp_confirm:{$service->id}:{$quantity}"],
            ];

            $buttons = [
                $qtyRow,
                $actionRow,
            ];
        }

        $this->replyOrSend($bot, $chatId, $editMessageId, $text, inlineKeyboard: [
            'inline_keyboard' => $buttons,
        ]);
    }

    protected function confirmKopkenOrder(
        TelegramBot $bot,
        $member,
        int|string $chatId,
        int $serviceId,
        int $quantity = 1,
        ?int $previewMessageId = null
    ): void {
        $quantity = max(1, min(5, $quantity));
        $service = OtpService::sellable()->whereKey($serviceId)->first() ?? $this->kopkenService();

        if (! $service) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $previewMessageId,
                'Layanan tidak tersedia.',
                removeInlineKeyboard: true
            );

            return;
        }

        $unitPrice = $bot->sellPriceFor($service->provider_price);
        $totalPrice = $unitPrice * $quantity;
        $available = $member->availableBalance();

        if ($available < $totalPrice) {
            $formattedTotal = 'Rp '.number_format($totalPrice, 0, ',', '.');
            $shortage = $totalPrice - $available;
            $formattedShortage = 'Rp '.number_format($shortage, 0, ',', '.');

            $this->replyOrSend(
                $bot,
                $chatId,
                $previewMessageId,
                "<b>Saldo Tidak Cukup</b> ⚠️\n\n"
                ."Total hold untuk <b>{$quantity}x nomor</b> adalah <b>{$formattedTotal}</b>.\n"
                ."Saldo tersedia: <b>".$member->formattedAvailable()."</b> (Kurang {$formattedShortage}).\n\n"
                ."Silakan pilih jumlah lebih sedikit atau lakukan deposit.",
                inlineKeyboard: [
                    'inline_keyboard' => [
                        [
                            ['text' => '➕ Deposit Saldo', 'callback_data' => 'deposit'],
                            ['text' => '📱 Pilih 1x Nomor', 'callback_data' => "otp_qty:{$service->id}:1"],
                        ],
                    ],
                ]
            );

            return;
        }

        $otpOrderService = app(OtpOrderService::class);
        $stock = $otpOrderService->getServiceStock($service, $bot);
        if ($stock <= 0) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $previewMessageId,
                "⚠️ <b>Stok Nomor Habis</b>\n\nStok nomor untuk layanan ini saat ini sedang habis. Silakan coba beberapa saat lagi.",
                removeInlineKeyboard: true
            );

            return;
        }

        // ── Bulk order (qty > 1): kirim satu loading bubble per slot ─────────
        if ($quantity > 1) {
            ignore_user_abort(true);
            @set_time_limit(180);

            $svcName = e($service->name ?? 'Kopken');
            $loadingMessageIds = [];

            for ($slot = 1; $slot <= $quantity; $slot++) {
                $slotLoadingText = "⏳ <b>Memeriksa Nomor #{$slot}</b>\n\n"
                    .'Mohon tunggu, sistem sedang memverifikasi nomor sebelum diberikan kepada Anda...';

                $msgId = null;
                for ($try = 1; $try <= 3 && ! $msgId; $try++) {
                    if ($slot === 1 && $try === 1) {
                        $msgId = $this->replyOrSend($bot, $chatId, $previewMessageId, $slotLoadingText, removeInlineKeyboard: true);
                    } else {
                        if ($try > 1 || $slot > 1) {
                            usleep(400000);
                        }
                        $msgId = $this->sendMessage($bot, $chatId, $slotLoadingText);
                    }
                }

                $loadingMessageIds[] = $msgId;
            }

            try {
                $orders = array_values($otpOrderService->requestBulkOtp($bot, $member, $service, $quantity));

                foreach ($orders as $idx => $o) {
                    $msgId = $loadingMessageIds[$idx] ?? null;
                    if ($msgId) {
                        $this->rememberOrderMessage($o, $msgId);
                    } else {
                        Log::warning("Bulk OTP slot #".($idx + 1)." tidak punya telegram_message_id (order {$o->id})");
                    }
                }

                foreach ($orders as $idx => $o) {
                    $slotNum = $idx + 1;
                    $orderText = $this->formatOrderCard(
                        $o,
                        title: "Order {$svcName} #{$slotNum} — Memeriksa Nomor 📲",
                        footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
                    );
                    $loadMsgId = $this->orderMessageId($o);
                    if ($loadMsgId) {
                        $this->editMessage($bot, $chatId, $loadMsgId, $orderText, $this->orderActionKeyboard($o), false);
                    }
                }

                try {
                    app(OtpOrderWatcher::class)->startBatch($orders);
                } catch (\Throwable $watchErr) {
                    Log::warning('Bulk OTP watcher failed to start: '.$watchErr->getMessage());
                }
            } catch (ValidationException $e) {
                // Tampilkan error di bubble pertama, hapus bubble loading extra
                foreach (array_slice($loadingMessageIds, 1) as $extraMsgId) {
                    if ($extraMsgId) {
                        try { $this->deleteMessage($bot, $chatId, $extraMsgId); } catch (\Throwable $deleteErr) {}
                    }
                }
                $this->replyOrSend(
                    $bot, $chatId, $loadingMessageIds[0] ?? null,
                    collect($e->errors())->flatten()->first() ?? 'Gagal membuat order.',
                    removeInlineKeyboard: true
                );
            } catch (\Throwable $e) {
                $errMessage = (string) $e->getMessage();
                if (stripos($errMessage, 'cURL error 28') !== false || stripos($errMessage, 'timed out') !== false || stripos($errMessage, 'Resolving timed out') !== false) {
                    $errMessage = 'Server pemesanan nomor sedang sibuk (koneksi timeout). Silakan coba pesan kembali.';
                }
                $isCancelledOrBanned = stripos($errMessage, 'terblokir') !== false
                    || stripos($errMessage, 'banned') !== false
                    || stripos($errMessage, 'dibatalkan') !== false
                    || stripos($errMessage, 'cancelled') !== false
                    || stripos($errMessage, 'canceled') !== false;

                foreach (array_slice($loadingMessageIds, 1) as $extraMsgId) {
                    if ($extraMsgId) {
                        try { $this->deleteMessage($bot, $chatId, $extraMsgId); } catch (\Throwable $deleteErr) {}
                    }
                }

                if ($isCancelledOrBanned) {
                    $this->replyOrSend(
                        $bot, $chatId, $loadingMessageIds[0] ?? null,
                        "❌ <b>Pesanan Dibatalkan</b>\n\n{$errMessage}",
                        inlineKeyboard: [
                            'inline_keyboard' => [
                                [['text' => '📱 Pesan nomor baru', 'callback_data' => 'otp_reorder:'.$service->id]],
                            ],
                        ]
                    );

                    return;
                }

                $this->replyOrSend(
                    $bot, $chatId, $loadingMessageIds[0] ?? null,
                    "⚠️ <b>Gagal Membuat Order</b>\n\n{$errMessage}",
                    removeInlineKeyboard: true
                );
            }

            return;
        }

        // ── Single order flow ────────────────────────────────────────────────
        $loadingText = "⏳ <b>Memeriksa Ketersediaan Nomor</b>\n\n"
            ."Mohon tunggu, sistem sedang memverifikasi nomor sebelum diberikan kepada Anda...";

        $workingMessageId = $this->replyOrSend(
            $bot,
            $chatId,
            $previewMessageId,
            $loadingText,
            removeInlineKeyboard: true
        );

        try {
            $order = $otpOrderService->requestOtp($bot, $member, $service);
            $this->rememberOrderMessage($order, $workingMessageId ?? $previewMessageId);
            try {
                app(OtpOrderWatcher::class)->start($order);
            } catch (\Throwable $watchErr) {
                Log::warning('OTP watcher failed to start: '.$watchErr->getMessage());
            }
        } catch (ValidationException $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $workingMessageId ?? $previewMessageId,
                collect($e->errors())->flatten()->first() ?? 'Gagal membuat order.',
                removeInlineKeyboard: true
            );
        } catch (\Throwable $e) {
            $errMessage = (string) $e->getMessage();
            if (stripos($errMessage, 'cURL error 28') !== false || stripos($errMessage, 'timed out') !== false || stripos($errMessage, 'Resolving timed out') !== false) {
                $errMessage = 'Server pemesanan nomor sedang sibuk (koneksi timeout). Silakan coba pesan kembali.';
            }

            $isCancelledOrBanned = stripos($errMessage, 'terblokir') !== false
                || stripos($errMessage, 'banned') !== false
                || stripos($errMessage, 'dibatalkan') !== false
                || stripos($errMessage, 'cancelled') !== false
                || stripos($errMessage, 'canceled') !== false;

            if ($isCancelledOrBanned) {
                $text = "❌ <b>Pesanan Dibatalkan</b>\n\n"
                    ."{$errMessage}";

                $this->replyOrSend(
                    $bot,
                    $chatId,
                    $workingMessageId ?? $previewMessageId,
                    $text,
                    inlineKeyboard: [
                        'inline_keyboard' => [
                            [['text' => '📱 Pesan nomor baru', 'callback_data' => 'otp_reorder:'.$service->id]],
                        ],
                    ]
                );

                return;
            }

            $this->replyOrSend(
                $bot,
                $chatId,
                $workingMessageId ?? $previewMessageId,
                "⚠️ <b>Gagal Membuat Order</b>\n\n{$errMessage}",
                removeInlineKeyboard: true
            );
        }
    }

    public function formatOrderCard(
        OtpOrder $order,
        ?string $title = null,
        ?string $footer = null,
        ?string $statusOverride = null
    ): string {
        $id = $order->provider_order_id ? substr((string) $order->provider_order_id, 0, 8) : (string) $order->id;
        $tz = config('app.timezone', 'Asia/Jakarta');
        $when = $order->created_at?->timezone($tz)->format('d-m-Y H:i') ?? '-';
        $svc = e($order->otpService?->name ?? 'Kopken');
        $phone = $this->formatPhoneNumber($order->phone_number);
        $phoneStr = $phone !== '' ? '<code>'.e($phone).'</code>' : '-';
        $otp = filled($order->otp_code) ? '<code>'.e((string) $order->otp_code).'</code>' : '-';
        $price = 'Rp '.number_format($order->sell_price, 0, ',', '.');

        $status = $statusOverride ?? match (strtolower((string) $order->status)) {
            'completed' => 'Berhasil',
            'pending' => 'Pending',
            'cancelled', 'canceled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => ucfirst((string) $order->status),
        };

        $lines = [];
        if ($title) {
            $lines[] = "<b>{$title}</b>\n\n";
        }

        $lines[] = "❏ ID: <code>{$id}</code>\n"
            ."├  Tanggal: {$when}\n"
            ."├  Service: {$svc}\n"
            ."├  Nomor: {$phoneStr}\n"
            ."├  OTP: {$otp}\n"
            ."├  Harga: {$price}\n"
            ."└  Status: {$status}";

        if ($footer) {
            $lines[] = "\n\n{$footer}";
        }

        return implode('', $lines);
    }

    public function formatBatchOrderCard(
        $orders,
        ?string $title = null,
        ?string $footer = null
    ): string {
        $orders = collect($orders);
        $first = $orders->first();
        $totalHold = $orders->where('wallet_status', 'held')->sum('sell_price');
        $totalRefunded = $orders->where('wallet_status', 'refunded')->sum('sell_price');

        $lines = [];
        if ($title) {
            $lines[] = "<b>{$title}</b>\n\n";
        }

        $numIcons = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣'];

        foreach ($orders as $index => $order) {
            $numIcon = $numIcons[$index] ?? ('#'.($index + 1));
            $phone = $this->formatPhoneNumber($order->phone_number);
            $phoneStr = $phone !== '' ? '<code>'.e($phone).'</code>' : '<i>Menunggu nomor...</i>';
            $otp = match (true) {
                filled($order->otp_code) => '<code>'.e((string) $order->otp_code).'</code> ✅',
                in_array(strtolower((string) $order->status), ['cancelled', 'canceled', 'banned'], true) => '<i>Dibatalkan / Banned</i> ❌',
                strtolower((string) $order->status) === 'expired' => '<i>Kedaluwarsa</i> ⏳',
                default => '<code>⏳ Menunggu OTP...</code>',
            };
            $status = match (strtolower((string) $order->status)) {
                'completed' => '<b>Berhasil</b> 🎉',
                'pending' => 'Pending',
                'cancelled', 'canceled' => 'Dibatalkan',
                'expired' => 'Kedaluwarsa',
                default => ucfirst((string) $order->status),
            };

            $lines[] = "{$numIcon} {$phoneStr}\n"
                ."├  OTP: {$otp}\n"
                ."└  Status: {$status}\n\n";
        }

        $formattedTotalHold = 'Rp '.number_format($totalHold, 0, ',', '.');
        $lines[] = "Total Hold: <b>{$formattedTotalHold}</b>";

        if ($totalRefunded > 0) {
            $lines[] = ' (Refund: Rp '.number_format($totalRefunded, 0, ',', '.').')';
        }

        if ($footer) {
            $lines[] = "\n{$footer}";
        }

        return implode('', $lines);
    }

    public function batchOrderActionKeyboard($orders): array
    {
        $orders = collect($orders);
        $pendingOrders = $orders->where('status', 'pending');
        $first = $orders->first();
        $serviceId = $first?->otp_service_id ?: 0;
        $batchId = $first?->batch_id;

        $buttons = [];

        if ($pendingOrders->isNotEmpty() && $batchId) {
            // Row 1: Individual change number buttons per sequence (e.g. [ 🔀 Ganti #1 ] [ 🔀 Ganti #2 ])
            $changeButtons = [];
            foreach ($orders as $index => $order) {
                if ($order->status === 'pending') {
                    $slotNum = $index + 1;
                    $changeButtons[] = [
                        'text' => "🔀 Ganti #{$slotNum}",
                        'callback_data' => 'otp_change:'.$order->id,
                    ];
                }
            }

            if (! empty($changeButtons)) {
                foreach (array_chunk($changeButtons, 3) as $chunk) {
                    $buttons[] = $chunk;
                }
            }

            // Row 2: Batch action buttons
            $buttons[] = [
                ['text' => '🔄 Cek Status Semua', 'callback_data' => 'otp_batch_status:'.$batchId],
                ['text' => '❌ Batalkan Semua', 'callback_data' => 'otp_batch_cancel:'.$batchId],
            ];
        } else {
            $buttons[] = [
                ['text' => '📱 Pesan Lagi', 'callback_data' => 'otp_reorder:'.$serviceId],
            ];
        }

        return ['inline_keyboard' => $buttons];
    }

    protected function sendBatchOrderCreatedMessage(TelegramBot $bot, int|string $chatId, array $orders, ?int $editMessageId = null): void
    {
        $ordersColl = collect($orders);
        $service = e($ordersColl->first()?->otpService?->name ?? 'Kopken');

        foreach ($ordersColl as $index => $order) {
            $slotNum = $index + 1;
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} #{$slotNum} Berhasil Dibuat 📲",
                footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
            );

            if ($index === 0) {
                $messageId = $this->replyOrSend($bot, $chatId, $editMessageId, $text, inlineKeyboard: $this->orderActionKeyboard($order));
            } else {
                $messageId = $this->sendMessage($bot, $chatId, $text, null, $this->orderActionKeyboard($order));
            }

            $this->rememberOrderMessage($order, $messageId);
        }
    }

    public function notifyBatchOrderUpdated(TelegramBot $bot, $member, $orders): void
    {
        $orders = collect($orders);
        if ($orders->isEmpty()) {
            return;
        }

        $memberFresh = $member->fresh();

        foreach ($orders as $index => $order) {
            $order = $order->fresh(['otpService', 'botMember']);
            if (! $order) {
                continue;
            }

            $messageId = $this->orderMessageId($order);
            $service = e($order->otpService?->name ?? 'Kopken');
            $slotNum = $index + 1;
            $status = strtolower((string) $order->status);

            if ($status === 'completed') {
                $text = $this->formatOrderCard(
                    $order,
                    title: "Order {$service} — OTP MASUK 🎉",
                    footer: 'Saldo tersedia: <b>'.$memberFresh->formattedAvailable().'</b>',
                    statusOverride: 'Berhasil'
                );
                $keyboard = $this->completedOrderKeyboard($order);
            } elseif ($status === 'expired') {
                $text = "⏳ <b>Pesanan #{$slotNum} Kedaluwarsa</b>\n\n"
                    ."Waktu pemesanan OTP untuk layanan <b>{$service}</b> telah habis.\n"
                    .'Saldo yang tertahan telah dikembalikan.';
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '📱 Pesan nomor baru', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)]],
                    ],
                ];
            } elseif (in_array($status, ['cancelled', 'canceled', 'banned', 'blocked', 'failed'], true)) {
                $phone = $this->formatPhoneNumber($order->phone_number);
                $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : '';
                $targetPhone = $phoneFormatted !== '' ? " {$phoneFormatted}" : '';
                $text = "❌ <b>Pesanan #{$slotNum} Dibatalkan</b>\n\n"
                    ."Nomor WhatsApp{$targetPhone} terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda.\n"
                    .'Saldo yang tertahan telah dikembalikan.';
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '📱 Pesan nomor baru', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)]],
                    ],
                ];
            } else {
                // Pending / masih diproses
                $text = $this->formatOrderCard(
                    $order,
                    title: "Order {$service} #{$slotNum} Sedang Diproses 📲",
                    footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
                );
                $keyboard = $this->orderActionKeyboard($order);
            }

            if ($messageId) {
                $edited = $this->editMessage($bot, $member->telegram_chat_id, $messageId, $text, $keyboard, false);
                if (! $edited && in_array($status, ['completed', 'cancelled', 'canceled', 'expired', 'banned', 'failed'], true)) {
                    // Fallback: kirim bubble baru jika bubble lama hilang
                    $newMsgId = $this->sendMessage($bot, $member->telegram_chat_id, $text, null, $keyboard);
                    $this->rememberOrderMessage($order, $newMsgId);
                }
            } else {
                $newMsgId = $this->sendMessage($bot, $member->telegram_chat_id, $text, null, $keyboard);
                $this->rememberOrderMessage($order, $newMsgId);
            }
        }
    }

    public function revealOrderCreated(TelegramBot $bot, $member, OtpOrder $order): void
    {
        if ($order->isPartOfBatch()) {
            $this->revealBatchOrderCreated($bot, $member, $order->getBatchOrders());

            return;
        }

        $fresh = $order->fresh(['otpService', 'botMember', 'telegramBot']);
        if (! $fresh) {
            return;
        }

        if (filled($fresh->otp_code) || $fresh->status === 'completed') {
            return;
        }

        if (in_array(strtolower((string) $fresh->status), ['cancelled', 'canceled', 'expired', 'banned', 'failed'], true)) {
            $this->notifyOrderCancelled($bot, $member, $fresh, $fresh->status);

            return;
        }

        $service = e($fresh->otpService?->name ?? 'Kopken');
        $text = $this->formatOrderCard(
            $fresh,
            title: "Order {$service} Berhasil Dibuat 📲",
            footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
        );

        $messageId = $this->orderMessageId($fresh);

        if ($messageId) {
            $this->editMessage(
                $bot,
                $member->telegram_chat_id,
                $messageId,
                $text,
                $this->orderActionKeyboard($fresh),
                false
            );
        }
    }

    public function revealBatchOrderCreated(TelegramBot $bot, $member, $orders): void
    {
        $ordersColl = collect($orders);
        if ($ordersColl->isEmpty()) {
            return;
        }

        $hasActive = $ordersColl->contains(fn ($o) => $o->status === 'pending');
        if (! $hasActive) {
            $this->notifyBatchOrderUpdated($bot, $member, $ordersColl);

            return;
        }

        foreach ($ordersColl as $index => $order) {
            $order = $order->fresh(['otpService', 'botMember']);
            if (! $order || filled($order->otp_code) || in_array(strtolower((string) $order->status), ['completed', 'cancelled', 'canceled', 'expired', 'banned', 'failed'], true)) {
                continue;
            }

            $messageId = $this->orderMessageId($order);
            if (! $messageId) {
                continue;
            }

            $service = e($order->otpService?->name ?? 'Kopken');
            $slotNum = $index + 1;

            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} #{$slotNum} Sedang Diproses 📲",
                footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
            );

            $this->editMessage($bot, $member->telegram_chat_id, $messageId, $text, $this->orderActionKeyboard($order), false);
        }
    }

    protected function sendOrderCreatedMessage(TelegramBot $bot, int|string $chatId, OtpOrder $order, ?int $editMessageId = null): void
    {
        if ($order->isPartOfBatch()) {
            $this->sendBatchOrderCreatedMessage($bot, $chatId, $order->getBatchOrders()->all(), $editMessageId);

            return;
        }

        if (in_array(strtolower((string) $order->status), ['cancelled', 'canceled', 'expired', 'banned'], true)) {
            $this->notifyOrderCancelled($bot, $order->botMember, $order, $order->status);

            return;
        }

        $service = e($order->otpService?->name ?? 'Kopken');
        $text = $this->formatOrderCard(
            $order,
            title: "Order {$service} Berhasil Dibuat 📲",
            footer: 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.'
        );

        $messageId = $this->replyOrSend(
            $bot,
            $chatId,
            $editMessageId,
            $text,
            inlineKeyboard: $this->orderActionKeyboard($order)
        );

        $this->rememberOrderMessage($order, $messageId);
    }

    public function notifyOrderCompleted(TelegramBot $bot, $member, OtpOrder $order): void
    {
        $order = $order->fresh(['otpService', 'botMember']) ?? $order;
        $service = e($order->otpService?->name ?? 'Kopken');
        $slot = $order->isPartOfBatch() ? $order->batchSlotNumber() : null;
        $title = $slot
            ? "Order {$service} #{$slot} — OTP MASUK 🎉"
            : "Order {$service} — OTP MASUK 🎉";

        $text = $this->formatOrderCard(
            $order,
            title: $title,
            footer: $order->status === 'completed'
                ? 'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>'
                : 'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.',
            statusOverride: $order->status === 'completed' ? 'Berhasil' : null
        );

        Log::info('notifyOrderCompleted', [
            'order' => $order->id,
            'message_id' => $this->orderMessageId($order),
            'status' => $order->status,
            'has_otp' => filled($order->otp_code),
        ]);

        if ($order->status === 'pending') {
            $this->pushOrderBubble($bot, $member, $order, $text, $this->orderActionKeyboard($order));

            return;
        }

        $keyboard = $this->completedOrderKeyboard($order);

        $this->pushOrderBubble($bot, $member, $order, $text, $keyboard);
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    protected function completedOrderKeyboard(OtpOrder $order): array
    {
        $buttons = [];
        if ($order->canResendOtp()) {
            $buttons[] = ['text' => '🔄 Minta Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id];
        }
        $buttons[] = ['text' => '📱 Pesan Lagi', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)];

        return ['inline_keyboard' => [$buttons]];
    }

    public function stripExpiredResendButtons(): int
    {
        $orders = OtpOrder::query()
            ->where('status', 'completed')
            ->whereNotNull('telegram_message_id')
            ->where('completed_at', '>=', now()->subHours(6))
            ->with(['otpService', 'botMember', 'telegramBot'])
            ->orderBy('id')
            ->limit(30)
            ->get();

        $stripped = 0;
        foreach ($orders as $order) {
            if ($order->canResendOtp() || Cache::has('otp_resend_stripped:'.$order->id)) {
                continue;
            }

            $bot = $order->telegramBot;
            $member = $order->botMember;
            if (! $bot || ! $member) {
                continue;
            }

            try {
                $this->notifyOrderCompleted($bot, $member, $order);
                Cache::put('otp_resend_stripped:'.$order->id, 1, now()->addHours(12));
                $stripped++;
            } catch (\Throwable $e) {
                Log::warning('stripExpiredResendButtons failed: '.$e->getMessage(), ['order' => $order->id]);
            }
        }

        return $stripped;
    }

    public function notifyOrderCancelled(
        TelegramBot $bot,
        $member,
        OtpOrder $order,
        string $reasonType = 'cancelled',
        ?string $customReason = null
    ): void {
        $phone = $this->formatPhoneNumber($order->phone_number);
        $phoneFormatted = $phone !== '' ? (str_starts_with($phone, '62') ? $phone : '62'.ltrim($phone, '0')) : '';
        $service = e($order->otpService?->name ?? 'Kopken');
        $slot = $order->isPartOfBatch() ? $order->batchSlotNumber() : null;
        $slotPrefix = $slot ? " #{$slot}" : '';

        if ($reasonType === 'expired') {
            $text = "⏳ <b>Pesanan{$slotPrefix} Kedaluwarsa</b>\n\n"
                ."Waktu pemesanan OTP untuk layanan <b>{$service}</b> telah habis.\n"
                ."Saldo yang tertahan telah dikembalikan.";
        } else {
            $targetPhone = $phoneFormatted !== '' ? " {$phoneFormatted}" : '';
            $text = "❌ <b>Pesanan{$slotPrefix} Dibatalkan</b>\n\n"
                ."Nomor WhatsApp{$targetPhone} terblokir/banned oleh WhatsApp, jadi tidak diberikan kepada Anda.\n"
                ."Saldo yang tertahan telah dikembalikan.";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📱 Pesan nomor baru', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)],
                ],
            ],
        ];

        $this->pushOrderBubble($bot, $member, $order, $text, $keyboard);
    }

    protected function pushOrderBubble(TelegramBot $bot, $member, OtpOrder $order, string $text, ?array $keyboard = null): void
    {
        $messageId = $this->orderMessageId($order);

        if ($messageId) {
            $edited = $this->editMessage(
                $bot,
                $member->telegram_chat_id,
                $messageId,
                $text,
                $keyboard,
                false
            );

            if ($edited) {
                return;
            }
        }

        $newMsgId = $this->sendMessage($bot, $member->telegram_chat_id, $text, null, $keyboard);
        $this->rememberOrderMessage($order, $newMsgId);
    }

    protected function rememberOrderMessage(OtpOrder $order, ?int $messageId): void
    {
        if (! $messageId) {
            return;
        }

        try {
            OtpOrder::query()->whereKey($order->id)->update(['telegram_message_id' => $messageId]);
            $order->telegram_message_id = $messageId;
        } catch (\Throwable $e) {
            Log::warning('Gagal simpan telegram_message_id: '.$e->getMessage());
        }

        Cache::put($this->orderMessageCacheKey($order->id), $messageId, now()->addHours(6));
    }

    protected function orderMessageId(OtpOrder $order): ?int
    {
        if ($order->telegram_message_id) {
            return (int) $order->telegram_message_id;
        }

        $cached = Cache::get($this->orderMessageCacheKey($order->id));

        return $cached ? (int) $cached : null;
    }

    protected function orderMessageCacheKey(int $orderId): string
    {
        return 'otp_order_tg_msg:'.$orderId;
    }

    public function formatPhoneNumber(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '62')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    protected function orderActionKeyboard(OtpOrder $order): array
    {
        $secondRow = [
            ['text' => '🔀 Ganti Nomor', 'callback_data' => 'otp_change:'.$order->id],
        ];
        if ($order->canResendOtp()) {
            $secondRow[] = ['text' => '🔄 Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id];
        }

        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 Cek OTP', 'callback_data' => 'otp_status:'.$order->id],
                ],
                $secondRow,
                [
                    ['text' => '❌ Batalkan', 'callback_data' => 'otp_cancel:'.$order->id],
                ],
            ],
        ];
    }

    protected function cancelPending(TelegramBot $bot, $member, $chatId, ?int $orderId = null, ?int $editMessageId = null): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->when($orderId, fn ($q) => $q->whereKey($orderId))
            ->latest()
            ->first();

        if (! $order) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                'Tidak ada order OTP yang sedang berjalan.',
                removeInlineKeyboard: true
            );

            return;
        }

        try {
            app(OtpOrderService::class)->cancelOrder($order);

            $service = e($order->otpService?->name ?? 'Kopken');
            $text = $this->formatOrderCard(
                $order->fresh(),
                title: "Order {$service} Dibatalkan ❌",
                footer: 'Saldo telah dikembalikan: <b>'.$member->fresh()->formattedAvailable().'</b>',
                statusOverride: 'Dibatalkan'
            );

            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId ?? $this->orderMessageId($order),
                $text,
                inlineKeyboard: [
                    'inline_keyboard' => [
                        [['text' => '📱 Pesan Lagi', 'callback_data' => 'otp_reorder:'.($order->otp_service_id ?: 0)]],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId ?? $this->orderMessageId($order),
                'Gagal membatalkan order: '.$e->getMessage(),
                inlineKeyboard: $this->orderActionKeyboard($order)
            );
        }
    }

    protected function statusBatchPending(
        TelegramBot $bot,
        $member,
        int|string $chatId,
        string $batchId,
        ?int $messageId = null,
        ?string $callbackId = null
    ): void {
        $orders = OtpOrder::query()
            ->where('batch_id', $batchId)
            ->where('bot_member_id', $member->id)
            ->get();

        if ($orders->isEmpty()) {
            $this->replyOrSend($bot, $chatId, $messageId, 'Pesanan tidak ditemukan.', removeInlineKeyboard: true);

            return;
        }

        $otpService = app(OtpOrderService::class);
        $freshOrders = collect();
        foreach ($orders as $order) {
            if ($order->status === 'pending') {
                $fresh = $otpService->refreshOrder($order);
                $freshOrders->push($fresh);
            } else {
                $freshOrders->push($order);
            }
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $checkTime = now()->timezone($tz)->format('H:i:s');
        $completedCount = $freshOrders->where('status', 'completed')->count();
        $totalCount = $freshOrders->count();

        if ($callbackId) {
            try {
                $toast = "Status diperbarui ({$completedCount}/{$totalCount} OTP masuk) pada {$checkTime} WIB";
                Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackId,
                    'text' => $toast,
                    'show_alert' => false,
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $this->notifyBatchOrderUpdated($bot, $member, $freshOrders);
    }

    protected function cancelBatchPending(
        TelegramBot $bot,
        $member,
        int|string $chatId,
        string $batchId,
        ?int $messageId = null
    ): void {
        $orders = OtpOrder::query()
            ->where('batch_id', $batchId)
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->get();

        $otpService = app(OtpOrderService::class);

        foreach ($orders as $order) {
            try {
                $otpService->cancelOrder($order);
            } catch (\Throwable $e) {
                Log::warning("Gagal cancel order #{$order->id} in batch: ".$e->getMessage());
            }
        }

        $allOrders = OtpOrder::query()
            ->where('batch_id', $batchId)
            ->where('bot_member_id', $member->id)
            ->get();

        $this->notifyBatchOrderUpdated($bot, $member, $allOrders);
    }

    protected function changePending(TelegramBot $bot, $member, $chatId, ?int $orderId = null, ?int $editMessageId = null): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->when($orderId, fn ($q) => $q->whereKey($orderId))
            ->latest()
            ->first();

        if (! $order) {
            $recent = OtpOrder::query()
                ->where('bot_member_id', $member->id)
                ->when($orderId, fn ($q) => $q->whereKey($orderId))
                ->latest()
                ->first();

            $msg = "Tidak ada order OTP yang sedang pending untuk ganti nomor.\n\n";
            if ($recent && $recent->status === 'completed') {
                $msg .= "Order sebelumnya sudah <b>SELESAI</b> (OTP sudah diterima).\nUntuk memesan nomor baru, silakan gunakan menu <b>📱 Order OTP</b>.";
            } else {
                $msg .= "Pilih <b>📱 Order OTP</b> untuk memesan nomor baru.";
            }

            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                $msg,
                removeInlineKeyboard: true
            );

            return;
        }

        $messageId = $editMessageId ?? $this->orderMessageId($order);

        $loadingText = "⏳ <b>Memeriksa Ketersediaan Nomor</b>\n\n"
            ."Mohon tunggu, sistem sedang memverifikasi nomor sebelum diberikan kepada Anda...";

        $workingMessageId = $this->replyOrSend(
            $bot,
            $chatId,
            $messageId,
            $loadingText,
            removeInlineKeyboard: true
        );
        $messageId = $workingMessageId ?? $messageId;

        try {
            $order = app(OtpOrderService::class)->changeNumber($order);
            try {
                app(OtpOrderWatcher::class)->start($order);
            } catch (\Throwable $watchErr) {
                Log::warning('OTP watcher failed to start: '.$watchErr->getMessage());
            }

            if ($order->isPartOfBatch()) {
                $this->notifyBatchOrderUpdated($bot, $member, $order->getBatchOrders());

                return;
            }

            $service = e($order->otpService?->name ?? 'Kopken');
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} — Nomor Diganti 🔀",
                footer: 'Nomor baru aktif. OTP masuk otomatis — bubble ini akan diupdate.'
            );

            $newId = $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                $text,
                inlineKeyboard: $this->orderActionKeyboard($order)
            );

            if ($newId) {
                $this->rememberOrderMessage($order, $newId);
            }
        } catch (\Throwable $e) {
            $errText = 'Gagal ganti nomor: '.$e->getMessage();
            if ($order->isPartOfBatch()) {
                $this->notifyBatchOrderUpdated($bot, $member, $order->getBatchOrders());
            } else {
                $this->replyOrSend(
                    $bot,
                    $chatId,
                    $messageId,
                    $errText,
                    inlineKeyboard: $this->orderActionKeyboard($order)
                );
            }
        }
    }

    protected function resendPending(TelegramBot $bot, $member, $chatId, ?int $orderId = null, ?int $editMessageId = null): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->whereIn('status', ['pending', 'completed'])
            ->with('otpService')
            ->when($orderId, fn ($q) => $q->whereKey($orderId))
            ->latest()
            ->first();

        if (! $order || ! $order->canResendOtp()) {
            if ($order && $order->status === 'completed') {
                $this->notifyOrderCompleted($bot, $member, $order);

                return;
            }

            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                'Waktu minta ulang OTP dari provider sudah habis.',
                inlineKeyboard: $order
                    ? $this->completedOrderKeyboard($order)
                    : ['inline_keyboard' => [[['text' => '📱 Pesan Lagi', 'callback_data' => 'otp_reorder:0']]]]
            );

            return;
        }

        $messageId = $editMessageId ?? $this->orderMessageId($order);

        try {
            app(OtpOrderService::class)->resend($order);

            // Clear otp fields so newly incoming OTP triggers notification
            $order->update([
                'otp_code' => null,
                'full_text' => null,
            ]);

            try {
                app(OtpOrderWatcher::class)->start($order);
            } catch (\Throwable $watchErr) {
                Log::warning('OTP watcher failed to start: '.$watchErr->getMessage());
            }

            $service = e($order->otpService?->name ?? 'Kopken');
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} — Ulang OTP 🔄",
                footer: 'Permintaan ulang OTP dikirim (gratis). Menunggu OTP masuk otomatis…',
                statusOverride: 'Menunggu OTP Baru'
            );

            $newId = $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                $text,
                inlineKeyboard: $this->completedOrderKeyboard($order)
            );

            if ($newId) {
                $this->rememberOrderMessage($order, $newId);
            }
        } catch (\Throwable $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                'Gagal mengirim ulang: '.$e->getMessage(),
                inlineKeyboard: $this->orderActionKeyboard($order)
            );
        }
    }

    protected function statusPending(TelegramBot $bot, $member, $chatId, ?int $orderId = null, ?int $editMessageId = null): void
    {
        $order = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->whereIn('status', ['pending', 'completed'])
            ->where(function ($q) {
                $q->whereNull('provider_expire_at')
                    ->orWhere('provider_expire_at', '>', now());
            })
            ->where('created_at', '>=', now()->subMinutes(25))
            ->when($orderId, fn ($q) => $q->whereKey($orderId))
            ->latest()
            ->first();

        if (! $order) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                "Tidak ada order aktif.\n\nPilih <b>Order OTP</b> untuk memulai.",
                removeInlineKeyboard: true
            );

            return;
        }

        $messageId = $editMessageId ?? $this->orderMessageId($order);
        $order = app(OtpOrderService::class)->refreshOrder($order);

        $service = e($order->otpService?->name ?? 'Kopken');

        if ($order->status === 'completed' && filled($order->otp_code)) {
            $text = $this->formatOrderCard(
                $order,
                title: "Order {$service} — OTP MASUK 🎉",
                footer: 'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>',
                statusOverride: 'Berhasil'
            );

            $keyboard = $this->completedOrderKeyboard($order);

            $newId = $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                $text,
                inlineKeyboard: $keyboard
            );

            if ($newId) {
                $this->rememberOrderMessage($order, $newId);
            }

            return;
        }

        $text = $this->formatOrderCard(
            $order,
            title: "Order {$service} 📲",
            footer: 'OTP akan masuk otomatis ke bubble ini.'
        );

        $newId = $this->replyOrSend(
            $bot,
            $chatId,
            $messageId,
            $text,
            inlineKeyboard: $this->orderActionKeyboard($order)
        );

        if ($newId) {
            $this->rememberOrderMessage($order, $newId);
        }
    }

    protected function handleCallback(TelegramBot $bot, array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $from = $callback['from'] ?? [];
        $fromId = (string) ($from['id'] ?? $chatId);
        $callbackId = $callback['id'] ?? null;

        if ($callbackId && ! str_starts_with($data, 'otp_check_stock:')) {
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

        $this->currentBot = $bot;
        $this->currentFromId = $fromId;
        $member = app(OtpOrderService::class)->findOrRegisterMember($bot, $from);

        if ($data === 'deposit') {
            if ($messageId) {
                $this->deleteMessage($bot, $chatId, $messageId);
            }
            $this->sendDepositInfo($bot, $chatId);

            return;
        }

        if ($data === 'otp_preview_cancel') {
            $this->replyOrSend(
                $bot,
                $chatId,
                $messageId ? (int) $messageId : null,
                "<b>Pesanan dibatalkan</b>\n\nTidak ada saldo yang ditahan.",
                removeInlineKeyboard: true
            );

            return;
        }

        if (str_starts_with($data, 'otp_check_stock:')) {
            $serviceId = (int) substr($data, strlen('otp_check_stock:'));
            $this->startOrderForService(
                $bot,
                $member,
                $chatId,
                $serviceId,
                $messageId ? (int) $messageId : null,
                forceFreshStock: true,
                callbackId: $callbackId
            );

            return;
        }

        if (str_starts_with($data, 'otp_qty:')) {
            $parts = explode(':', substr($data, strlen('otp_qty:')));
            $serviceId = (int) ($parts[0] ?? 0);
            $qty = (int) ($parts[1] ?? 1);
            $this->startOrderForService(
                $bot,
                $member,
                $chatId,
                $serviceId,
                $messageId ? (int) $messageId : null,
                quantity: $qty
            );

            return;
        }

        if (str_starts_with($data, 'otp_confirm:')) {
            $parts = explode(':', substr($data, strlen('otp_confirm:')));
            $serviceId = (int) ($parts[0] ?? 0);
            $qty = (int) ($parts[1] ?? 1);
            $this->confirmKopkenOrder($bot, $member, $chatId, $serviceId, $qty, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_batch_status:')) {
            $batchId = substr($data, strlen('otp_batch_status:'));
            $this->statusBatchPending($bot, $member, $chatId, $batchId, $messageId ? (int) $messageId : null, $callbackId);

            return;
        }

        if (str_starts_with($data, 'otp_batch_cancel:')) {
            $batchId = substr($data, strlen('otp_batch_cancel:'));
            $this->cancelBatchPending($bot, $member, $chatId, $batchId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_reorder:')) {
            $serviceId = (int) substr($data, strlen('otp_reorder:'));
            $this->startOrderForService($bot, $member, $chatId, $serviceId ?: null);

            return;
        }

        if (str_starts_with($data, 'otp_status:')) {
            $orderId = (int) substr($data, strlen('otp_status:'));
            $this->statusPending($bot, $member, $chatId, $orderId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_change:')) {
            $orderId = (int) substr($data, strlen('otp_change:'));
            $this->changePending($bot, $member, $chatId, $orderId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_resend:')) {
            $orderId = (int) substr($data, strlen('otp_resend:'));
            $this->resendPending($bot, $member, $chatId, $orderId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_cancel:')) {
            $orderId = (int) substr($data, strlen('otp_cancel:'));
            $this->cancelPending($bot, $member, $chatId, $orderId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'otp_view_order:')) {
            $orderId = (int) substr($data, strlen('otp_view_order:'));
            $this->showOrderDetail($bot, $member, $chatId, $orderId, $messageId ? (int) $messageId : null);

            return;
        }

        if (str_starts_with($data, 'admin_')) {
            if (! $bot->isTelegramAdmin($fromId)) {
                $this->sendMessage($bot, $chatId, 'Akses admin ditolak.', $this->mainKeyboard());

                return;
            }

            if ($messageId) {
                $this->deleteMessage($bot, $chatId, $messageId);
            }

            if ($data === 'admin_rekap') {
                $this->sendAdminDailyRecap($bot, $chatId);

                return;
            }

            if ($data === 'admin_cek') {
                $this->startAdminCekPrompt($bot, $chatId);

                return;
            }

            if ($data === 'admin_adddeposit') {
                $this->startAdminDepositPrompt($bot, $chatId);

                return;
            }

            if ($data === 'admin_user_menu') {
                $this->clearAdminPending($bot, $chatId);
                $this->sendMessage($bot, $chatId, "<b>Menu User</b>\n\nSilakan pilih menu di bawah.", $this->mainKeyboard());
            }
        }
    }

    public function deleteMessage(TelegramBot $bot, int|string $chatId, int $messageId): void
    {
        try {
            Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/deleteMessage", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteMessage failed: '.$e->getMessage());
        }
    }

    /**
     * Edit existing bubble when possible; otherwise send a new message.
     * When $messageId is set, never fall back to a new bubble (avoids spam).
     */
    protected function replyOrSend(
        TelegramBot $bot,
        int|string $chatId,
        ?int $messageId,
        string $text,
        ?array $replyMarkup = null,
        ?array $inlineKeyboard = null,
        bool $removeInlineKeyboard = false
    ): ?int {
        if ($messageId) {
            $this->editMessage(
                $bot,
                $chatId,
                $messageId,
                $text,
                $inlineKeyboard,
                $removeInlineKeyboard
            );

            return $messageId;
        }

        return $this->sendMessage($bot, $chatId, $text, $replyMarkup, $inlineKeyboard);
    }

    public function editMessage(
        TelegramBot $bot,
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $inlineKeyboard = null,
        bool $removeInlineKeyboard = false
    ): bool {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($removeInlineKeyboard) {
            $payload['reply_markup'] = ['inline_keyboard' => []];
        } elseif ($inlineKeyboard) {
            $payload['reply_markup'] = $inlineKeyboard;
        }

        $data = $this->telegramApi($bot, 'editMessageText', $payload);
        if ($data === null) {
            return false;
        }

        if (($data['ok'] ?? false) || ($data['not_modified'] ?? false)) {
            return true;
        }

        $description = strtolower((string) ($data['description'] ?? ''));
        if (str_contains($description, 'message is not modified')) {
            return true;
        }

        Log::warning('Telegram editMessage failed: '.$description);

        return false;
    }

    public function sendMessage(
        TelegramBot $bot,
        int|string $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?array $inlineKeyboard = null
    ): ?int {
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

        $data = $this->telegramApi($bot, 'sendMessage', $payload);
        if (! ($data['ok'] ?? false)) {
            Log::error('Telegram sendMessage rejected: '.($data['description'] ?? 'unknown'));

            return null;
        }

        $messageId = $data['result']['message_id'] ?? null;

        return $messageId !== null ? (int) $messageId : null;
    }

    /**
     * POST to Telegram Bot API with retry on 429 / transient errors.
     *
     * @return array<string, mixed>|null
     */
    protected function telegramApi(TelegramBot $bot, string $method, array $payload): ?array
    {
        $url = "https://api.telegram.org/bot{$bot->token}/{$method}";

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::timeout(20)->asJson()->post($url, $payload);
                $data = $response->json();
                if (is_array($data) && ($data['ok'] ?? false)) {
                    return $data;
                }

                $description = strtolower((string) (is_array($data) ? ($data['description'] ?? '') : $response->body()));
                $retryAfter = (int) data_get($data, 'parameters.retry_after', 0);

                if ($response->status() === 429 || str_contains($description, 'too many') || str_contains($description, 'retry after')) {
                    sleep(max(1, min($retryAfter ?: $attempt, 5)));
                    continue;
                }

                if (str_contains($description, 'message is not modified')) {
                    return ['ok' => true, 'not_modified' => true];
                }

                Log::warning("Telegram {$method} failed: ".$description);

                return is_array($data) ? $data : null;
            } catch (\Throwable $e) {
                Log::warning("Telegram {$method} error: ".$e->getMessage());
                if ($attempt < 4) {
                    usleep(300000 * $attempt);
                    continue;
                }
            }
        }

        return null;
    }

    public function checkAndAlertProviderBalance(TelegramBot $bot, ?OtpProviderClient $client = null): array
    {
        if (! $bot->token || ! filled($bot->otp_api_key)) {
            return [
                'bot_id' => $bot->id,
                'name' => $bot->name,
                'status' => 'skipped',
                'reason' => 'Token atau OTP API Key belum diisi',
            ];
        }

        $client = $client ?: app(OtpProviderClient::class);

        try {
            $data = $client->forBot($bot)->getBalance();
            $balance = (int) ($data['balance'] ?? 0);
            $currency = (string) ($data['currency'] ?? 'IDR');

            $bot->update([
                'provider_balance' => $balance,
                'provider_balance_currency' => $currency,
                'provider_balance_checked_at' => now(),
            ]);

            $threshold = (int) ($bot->min_provider_balance_alert ?? 0);
            $alertSent = false;
            $adminsNotified = [];

            if ($threshold > 0 && $balance <= $threshold) {
                // Throttling: send at most once every 60 minutes per bot
                $lastAlert = $bot->provider_balance_last_alerted_at;
                $canAlert = ! $lastAlert || $lastAlert->diffInMinutes(now()) >= 60;

                if ($canAlert) {
                    $adminIds = $bot->adminTelegramIdList();
                    $formattedBalance = number_format($balance, 0, ',', '.');
                    $formattedThreshold = number_format($threshold, 0, ',', '.');

                    $alertText = "⚠️ <b>PERINGATAN: SALDO PUSAT DIBAWAH AMBANG!</b>\n\n"
                        ."Saldo API provider bot <b>{$bot->name}</b> tersisa:\n"
                        ."💰 <b>Rp{$formattedBalance}</b>\n\n"
                        ."Batas Ambang Minimal: <b>Rp{$formattedThreshold}</b>\n\n"
                        ."🔔 <i>Segera isi/topup saldo pusat provider Anda agar layanan bot dan transaksi OTP member tetap lancar!</i>";

                    foreach ($adminIds as $adminId) {
                        try {
                            $res = $this->sendMessage($bot, $adminId, $alertText);
                            if ($res !== null) {
                                $adminsNotified[] = $adminId;
                                $alertSent = true;
                            }
                        } catch (\Throwable $e) {
                            Log::warning("Failed to send balance alert to admin {$adminId}: ".$e->getMessage());
                        }
                    }

                    if ($alertSent) {
                        $bot->update(['provider_balance_last_alerted_at' => now()]);
                    }
                }
            }

            return [
                'bot_id' => $bot->id,
                'name' => $bot->name,
                'balance' => $balance,
                'threshold' => $threshold,
                'is_low' => $threshold > 0 && $balance <= $threshold,
                'alert_sent' => $alertSent,
                'admins_notified' => $adminsNotified,
                'status' => 'success',
            ];
        } catch (\Throwable $e) {
            Log::error("Failed checking provider balance for bot {$bot->id}: ".$e->getMessage());

            return [
                'bot_id' => $bot->id,
                'name' => $bot->name,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}
