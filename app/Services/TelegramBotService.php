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
            ."• Order OTP — preview konfirmasi lalu pesan nomor\n"
            ."• Saldo — cek saldo & hold\n"
            ."• Deposit — hubungi admin (manual)\n"
            ."• Akun — nama, ID Telegram, status\n"
            ."• Status — pantau order berjalan\n"
            ."• Riwayat — 5 transaksi terakhir\n"
            ."• Cek OTP — refresh manual (opsional)\n"
            ."• Ulang OTP — minta ulang kode (gratis)\n"
            ."• Ganti Nomor — ganti nomor pending\n"
            ."• Batalkan — batalkan & kembalikan hold\n"
            ."• Bantuan — panduan ini\n\n"
            ."<b>Perintah teks</b>\n"
            ."/otp · /saldo · /deposit · /akun · /status · /ulang · /ganti · /batal\n\n"
            .'Saldo ditahan saat order. OTP masuk otomatis ke bubble order (status jadi SELESAI).';
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

        $pending = OtpOrder::query()
            ->where('bot_member_id', $member->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pending) {
            $this->sendMessage(
                $bot,
                $chatId,
                "<b>Masih ada order aktif</b>\n\n".
                'Nomor: <code>'.e((string) $pending->phone_number)."</code>\n".
                'Hold: <b>Rp'.number_format($pending->sell_price, 0, ',', '.')."</b>\n".
                "Status: <b>PENDING</b>\n\n".
                'Selesaikan / batalkan order ini dulu sebelum order baru.',
                null,
                $this->orderActionKeyboard($pending)
            );

            return;
        }

        $price = $bot->sellPriceFor($service->provider_price);
        $available = $member->availableBalance();

        if ($available < $price) {
            $this->sendMessage(
                $bot,
                $chatId,
                "<b>Saldo tidak cukup</b>\n\n".
                'Dibutuhkan: <b>Rp'.number_format($price, 0, ',', '.')."</b>\n".
                'Tersedia: <b>'.$member->formattedAvailable()."</b>\n\n".
                'Silakan deposit dulu lewat menu Deposit.',
                $this->mainKeyboard()
            );

            return;
        }

        $text = "<b>Konfirmasi Order OTP</b>\n\n"
            .'Layanan: <b>'.e($service->name)."</b>\n"
            .'Harga (hold): <b>Rp'.number_format($price, 0, ',', '.')."</b>\n"
            .'Saldo tersedia: <b>'.$member->formattedAvailable()."</b>\n\n"
            ."Jika dilanjutkan, sistem akan memesan nomor dan menahan saldo.\n"
            .'Saldo baru dipotong saat OTP masuk.';

        $this->sendMessage($bot, $chatId, $text, null, [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Ya, Order', 'callback_data' => 'otp_confirm:'.$service->id],
                    ['text' => '❌ Batal', 'callback_data' => 'otp_preview_cancel'],
                ],
            ],
        ]);
    }

    protected function confirmKopkenOrder(TelegramBot $bot, $member, int|string $chatId, int $serviceId, ?int $previewMessageId = null): void
    {
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

        try {
            $order = app(OtpOrderService::class)->requestOtp($bot, $member, $service);
            $this->sendOrderCreatedMessage($bot, $chatId, $order, $previewMessageId);
            app(OtpOrderWatcher::class)->start($order);
        } catch (ValidationException $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $previewMessageId,
                collect($e->errors())->flatten()->first() ?? 'Gagal membuat order.',
                removeInlineKeyboard: true
            );
        } catch (\Throwable $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $previewMessageId,
                'Gagal: '.$e->getMessage(),
                removeInlineKeyboard: true
            );
        }
    }

    protected function sendOrderCreatedMessage(TelegramBot $bot, int|string $chatId, OtpOrder $order, ?int $editMessageId = null): void
    {
        $text = "<b>Order KOPKEN berhasil dibuat</b>\n\n".
            'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
            'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n".
            "Status: <b>PENDING</b>\n\n".
            'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.';

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
        $service = e($order->otpService?->name ?? 'OTP');
        $text = "<b>Order {$service} — OTP MASUK</b>\n\n".
            'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
            'OTP: <code>'.e((string) $order->otp_code)."</code>\n".
            "Status: <b>SELESAI</b>\n".
            'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n\n".
            'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id],
                ],
            ],
        ];

        $messageId = $this->orderMessageId($order);

        if ($messageId) {
            $edited = $this->editMessage(
                $bot,
                $member->telegram_chat_id,
                $messageId,
                $text,
                $keyboard,
                true
            );

            if ($edited) {
                return;
            }
        }

        // Fallback only if bubble hilang / belum tersimpan — tetap kirim OTP ke user.
        $this->sendMessage($bot, $member->telegram_chat_id, $text, null, $keyboard);
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

    protected function orderActionKeyboard(OtpOrder $order): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔎 Cek OTP', 'callback_data' => 'otp_status:'.$order->id],
                    ['text' => '🔀 Ganti Nomor', 'callback_data' => 'otp_change:'.$order->id],
                ],
                [
                    ['text' => '🔄 Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id],
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
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId ?? $this->orderMessageId($order),
                "<b>Pesanan dibatalkan</b>\n\n".
                "Hold saldo sudah dikembalikan.\n".
                'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>',
                removeInlineKeyboard: true
            );
        } catch (\Throwable $e) {
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                'Gagal membatalkan: '.$e->getMessage(),
                removeInlineKeyboard: true
            );
        }
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
            $this->replyOrSend(
                $bot,
                $chatId,
                $editMessageId,
                'Tidak ada order OTP yang sedang berjalan.',
                removeInlineKeyboard: true
            );

            return;
        }

        $messageId = $editMessageId ?? $this->orderMessageId($order);

        try {
            $order = app(OtpOrderService::class)->changeNumber($order);
            $service = e($order->otpService?->name ?? 'OTP');
            $text = "<b>Order {$service} — nomor diganti</b>\n\n".
                'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
                'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n".
                "Status: <b>PENDING</b>\n\n".
                'Saldo ditahan. OTP masuk otomatis — bubble ini akan diupdate.';

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
            $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                'Gagal ganti nomor: '.$e->getMessage(),
                inlineKeyboard: $this->orderActionKeyboard($order)
            );
        }
    }

    protected function resendPending(TelegramBot $bot, $member, $chatId, ?int $orderId = null, ?int $editMessageId = null): void
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
                'Tidak ada order OTP yang sedang berjalan atau aktif.',
                removeInlineKeyboard: true
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

            // Start watcher to poll for the new OTP
            app(OtpOrderWatcher::class)->start($order);

            $service = e($order->otpService?->name ?? 'OTP');
            $text = "<b>Order {$service} — Ulang OTP</b>\n\n".
                'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
                "Status: <b>MENUNGGU OTP BARU</b>\n\n".
                'Permintaan ulang OTP dikirim (gratis). Menunggu OTP masuk otomatis…';

            $newId = $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                $text,
                inlineKeyboard: [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id],
                            ['text' => '🔎 Cek OTP', 'callback_data' => 'otp_status:'.$order->id],
                        ],
                    ],
                ]
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

        $service = e($order->otpService?->name ?? 'OTP');

        if ($order->status === 'completed' && filled($order->otp_code)) {
            $text = "<b>Order {$service} — OTP MASUK</b>\n\n".
                'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
                'OTP: <code>'.e((string) $order->otp_code)."</code>\n".
                "Status: <b>SELESAI</b>\n".
                'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n\n".
                'Saldo tersedia: <b>'.$member->fresh()->formattedAvailable().'</b>';

            $newId = $this->replyOrSend(
                $bot,
                $chatId,
                $messageId,
                $text,
                inlineKeyboard: [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Ulang OTP', 'callback_data' => 'otp_resend:'.$order->id],
                        ],
                    ],
                ]
            );

            if ($newId) {
                $this->rememberOrderMessage($order, $newId);
            }

            return;
        }

        $text = "<b>Order {$service}</b>\n\n".
            'Nomor: <code>'.e((string) $order->phone_number)."</code>\n".
            'Hold: <b>Rp'.number_format($order->sell_price, 0, ',', '.')."</b>\n".
            'Status: <b>'.e(strtoupper($order->status))."</b>\n".
            'OTP: '.(filled($order->otp_code) ? '<code>'.e((string) $order->otp_code).'</code>' : '<b>belum masuk</b>')."\n\n".
            'OTP akan masuk otomatis ke bubble ini.';

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

        if (str_starts_with($data, 'otp_confirm:')) {
            $serviceId = (int) substr($data, strlen('otp_confirm:'));
            $this->confirmKopkenOrder($bot, $member, $chatId, $serviceId, $messageId ? (int) $messageId : null);

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
        try {
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

            $response = Http::asJson()->post("https://api.telegram.org/bot{$bot->token}/editMessageText", $payload);

            if ($response->successful()) {
                return true;
            }

            $description = strtolower((string) $response->json('description', ''));

            // Same content → Telegram rejects; treat as already up to date.
            if (str_contains($description, 'message is not modified')) {
                return true;
            }

            Log::warning('Telegram editMessage failed: '.$description);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Telegram editMessage failed: '.$e->getMessage());

            return false;
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
            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                Log::error('Telegram sendMessage rejected: '.($data['description'] ?? $response->body()));

                return null;
            }

            $messageId = $data['result']['message_id'] ?? null;

            return $messageId !== null ? (int) $messageId : null;
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage error: '.$e->getMessage());

            return null;
        }
    }
}
