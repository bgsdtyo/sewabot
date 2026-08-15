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
            ->whereDate('created_at', Carbon::today(config('app.timezone')))
            ->count();
        $joined = $target->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') ?? '-';
        $username = $target->telegram_username ? '@'.ltrim($target->telegram_username, '@') : '-';

        $text = "<b>Data Member</b>\n\n"
            .'Nama: <b>'.e($target->telegram_name ?: '-')."</b>\n"
            .'Username: <b>'.e($username)."</b>\n"
            .'Telegram ID: <code>'.e((string) $target->telegram_chat_id)."</code>\n"
            .'Status: <b>'.($target->is_active ? 'Aktif' : 'Nonaktif')."</b>\n"
            .'Terdaftar: <b>'.e($joined)."</b>\n\n"
            ."<b>Saldo</b>\n"
            .'Total: <b>'.$target->formattedBalance()."</b>\n"
            .'Tersedia: <b>'.$target->formattedAvailable()."</b>\n"
            .'Ditahan: <b>Rp'.number_format($target->held_balance, 0, ',', '.')."</b>\n\n"
            ."Order OTP: <b>{$orders}</b> (hari ini: <b>{$ordersToday}</b>)";

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
            [['text' => '📦 Status'], ['text' => '📋 Riwayat']],
            [['text' => '🔄 Ulang OTP'], ['text' => '🔀 Ganti Nomor']],
            [['text' => '❌ Batalkan'], ['text' => '❓ Bantuan']],
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
        $joined = $member->created_at
            ? $member->created_at->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i')
            : '-';
        $status = $member->is_active ? 'Aktif' : 'Nonaktif';

        $text = "<b>Informasi Akun</b>\n\n"
            .'Nama: <b>'.e($name)."</b>\n"
            .'Username: <b>'.e($username)."</b>\n"
            .'Telegram ID: <code>'.e((string) $telegramId)."</code>\n"
            .'Status: <b>'.e($status)."</b>\n"
            .'Terdaftar: <b>'.e($joined)."</b>\n\n"
            ."<b>Saldo</b>\n"
            .'Total: <b>'.$member->formattedBalance()."</b>\n"
            .'Tersedia: <b>'.$member->formattedAvailable()."</b>\n"
            .'Ditahan: <b>Rp'.number_format($member->held_balance, 0, ',', '.').'</b>';

        $this->sendMessage($bot, $chatId, $text, $this->mainKeyboard());
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
        $bank = trim((string) ($bot->deposit_bank_name ?? ''));
        $number = trim((string) ($bot->deposit_account_number ?? ''));
        $name = trim((string) ($bot->deposit_account_name ?? ''));

        $text = "<b>Deposit Saldo</b>\n\n"
            ."Deposit dilakukan secara manual. Transfer sesuai data di bawah, lalu kirim bukti ke admin.\n\n";

        if ($bank !== '' || $number !== '' || $name !== '') {
            $text .= "<b>Tujuan transfer</b>\n";
            if ($bank !== '') {
                $text .= 'Bank / E-Wallet: <b>'.e($bank)."</b>\n";
            }
            if ($number !== '') {
                $text .= 'No. Rekening / HP: <code>'.e($number)."</code>\n";
            }
            if ($name !== '') {
                $text .= 'Atas nama: <b>'.e($name)."</b>\n";
            }
            $text .= "\n";
        } else {
            $text .= "<i>Data rekening belum diisi pemilik bot.</i>\n\n";
        }

        $text .= 'Setelah transfer, hubungi admin lewat tombol di bawah dan kirim bukti pembayaran.';

        $row = [];
        if ($wa = $bot->depositWhatsappUrl()) {
            $row[] = ['text' => '💬 WhatsApp', 'url' => $wa];
        }
        if ($tg = $bot->depositTelegramUrl()) {
            $row[] = ['text' => '✈️ Telegram', 'url' => $tg];
        }

        if ($row === []) {
            $text .= "\n\n<i>Kontak admin belum dikonfigurasi di Konfigurasi Bot.</i>";
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
            ."• Akun — nama, ID Telegram, status\n"
            ."• Status — pantau order berjalan\n"
            ."• Riwayat — 5 transaksi terakhir\n"
            ."• Ulang OTP — minta ulang kode (gratis)\n"
            ."• Ganti Nomor — ganti nomor pending\n"
            ."• Batalkan — batalkan & refund hold\n"
            ."• Bantuan — panduan ini\n\n"
            ."<b>Perintah teks</b>\n"
            ."/otp · /saldo · /deposit · /akun · /status · /ulang · /ganti · /batal\n\n"
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
        $messageId = $callback['message']['message_id'] ?? null;
        $fromId = (string) ($callback['from']['id'] ?? $chatId);
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

        $this->currentBot = $bot;
        $this->currentFromId = $fromId;

        if ($data === 'deposit') {
            if ($messageId) {
                $this->deleteMessage($bot, $chatId, $messageId);
            }
            $this->sendDepositInfo($bot, $chatId);

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

    public function sendMessage(
        TelegramBot $bot,
        int|string $chatId,
        string $text,
        ?array $replyMarkup = null,
        ?array $inlineKeyboard = null
    ): ?int {
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

            $response = Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/sendMessage", $payload);

            return $response->json('result.message_id');
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());

            return null;
        }
    }
}
