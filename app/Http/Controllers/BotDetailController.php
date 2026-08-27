<?php

namespace App\Http\Controllers;

use App\Models\BotMember;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use App\Services\OtpProviderManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BotDetailController extends Controller
{
    public function show(TelegramBot $telegramBot, OtpOrderService $otp): View
    {
        $this->authorizeOwner($telegramBot);

        $telegramBot->load('product');
        $services = OtpService::sellable()
            ->forProvider($telegramBot->activeOtpProvider())
            ->orderBy('name')
            ->get();

        if ($services->isEmpty() && $telegramBot->hasOtpConfigured()) {
            try {
                $otp->syncServices(['KOPKEN', 'WHATSAPP', 'WA', 'KOPI KENANGAN', 'KOPIKENANGAN'], $telegramBot);
                $services = OtpService::sellable()
                    ->forProvider($telegramBot->activeOtpProvider())
                    ->orderBy('name')
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('Auto-sync services on bot detail show: '.$e->getMessage());
            }
        }

        return view('bots.show', compact('telegramBot', 'services'));
    }

    public function updateSettings(Request $request, TelegramBot $telegramBot, OtpOrderService $otp): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);

        $data = $request->validate([
            'otp_provider' => ['nullable', 'string', 'in:kopken,wahub'],
            'otp_api_key' => ['nullable', 'string', 'max:500'],
            'otp_wahub_api_key' => ['nullable', 'string', 'max:500'],
            'otp_markup_type' => ['required', 'in:percent,flat'],
            'otp_markup_percent' => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_provider_balance_alert' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'deposit_whatsapp' => ['nullable', 'string', 'max:100'],
            'deposit_telegram' => ['nullable', 'string', 'max:100'],
            'deposit_bank_name' => ['nullable', 'string', 'max:100'],
            'deposit_account_number' => ['nullable', 'string', 'max:100'],
            'deposit_account_name' => ['nullable', 'string', 'max:100'],
            'deposit_note' => ['nullable', 'string', 'max:1000'],
            'admin_telegram_ids' => ['nullable', 'string', 'max:500'],
        ]);

        $adminIds = collect(preg_split('/[\s,;]+/', trim((string) ($data['admin_telegram_ids'] ?? ''))) ?: [])
            ->map(fn ($id) => preg_replace('/\D+/', '', (string) $id) ?: '')
            ->filter()
            ->unique()
            ->values();

        if ($adminIds->contains(fn ($id) => strlen($id) < 5)) {
            return back()
                ->withInput()
                ->withErrors(['admin_telegram_ids' => 'Setiap Admin Telegram ID minimal 5 digit angka.']);
        }

        $minAlert = isset($data['min_provider_balance_alert']) && $data['min_provider_balance_alert'] !== null && $data['min_provider_balance_alert'] !== ''
            ? (int) $data['min_provider_balance_alert']
            : null;

        $updates = [
            'otp_provider' => $data['otp_provider'] ?? $telegramBot->activeOtpProvider(),
            'otp_markup_type' => $data['otp_markup_type'],
            'otp_markup_percent' => (int) $data['otp_markup_percent'],
            'min_provider_balance_alert' => $minAlert && $minAlert > 0 ? $minAlert : null,
            'deposit_whatsapp' => filled($data['deposit_whatsapp'] ?? null) ? trim($data['deposit_whatsapp']) : null,
            'deposit_telegram' => filled($data['deposit_telegram'] ?? null) ? trim($data['deposit_telegram']) : null,
            'deposit_bank_name' => filled($data['deposit_bank_name'] ?? null) ? trim($data['deposit_bank_name']) : null,
            'deposit_account_number' => filled($data['deposit_account_number'] ?? null) ? trim($data['deposit_account_number']) : null,
            'deposit_account_name' => filled($data['deposit_account_name'] ?? null) ? trim($data['deposit_account_name']) : null,
            'deposit_note' => filled($data['deposit_note'] ?? null) ? trim($data['deposit_note']) : null,
            'admin_telegram_ids' => $adminIds->isNotEmpty() ? $adminIds->implode(', ') : null,
        ];

        if ($request->boolean('clear_api_key')) {
            $updates['otp_api_key'] = null;
        } elseif (filled($data['otp_api_key'] ?? null)) {
            $updates['otp_api_key'] = trim($data['otp_api_key']);
        }

        if ($request->boolean('clear_wahub_api_key')) {
            $updates['otp_wahub_api_key'] = null;
        } elseif (filled($data['otp_wahub_api_key'] ?? null)) {
            $updates['otp_wahub_api_key'] = trim($data['otp_wahub_api_key']);
        }

        try {
            $telegramBot->update($updates);

            if ($telegramBot->hasOtpConfigured()) {
                try {
                    $otp->syncServices(['KOPKEN', 'WHATSAPP', 'WA', 'KOPI KENANGAN', 'KOPIKENANGAN'], $telegramBot);
                } catch (\Throwable $e) {
                    Log::warning('Auto-sync services after update settings: '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'admin_telegram_ids' => 'Gagal menyimpan konfigurasi. Detail: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('bots.show', $telegramBot)
            ->with('success', 'Konfigurasi bot disimpan.');
    }

    public function syncServices(TelegramBot $telegramBot, OtpOrderService $otp): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);

        try {
            $count = $otp->syncServices(['KOPKEN', 'WHATSAPP', 'WA', 'KOPI KENANGAN', 'KOPIKENANGAN'], $telegramBot);
            $providerName = $telegramBot->otpProviderName();

            return back()->with('success', "Sync layanan OTP berhasil ({$count} layanan) untuk {$providerName}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['otp_api_key' => $e->getMessage()]);
        }
    }

    public function checkProviderBalance(TelegramBot $telegramBot, OtpProviderManager $manager): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);

        try {
            $data = $manager->forBot($telegramBot)->getBalance();
            $balance = (int) ($data['balance'] ?? $data['available'] ?? 0);
            $currency = (string) ($data['currency'] ?? 'IDR');

            $telegramBot->update([
                'provider_balance' => $balance,
                'provider_balance_currency' => $currency,
                'provider_balance_checked_at' => now(),
            ]);

            return redirect()
                ->route('bots.show', $telegramBot)
                ->with('success', 'Saldo pusat ('.$telegramBot->otpProviderName().') diperbarui: Rp'.number_format($balance, 0, ',', '.'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('bots.show', $telegramBot)
                ->withErrors(['provider_balance' => $e->getMessage()]);
        }
    }

    public function topup(Request $request, TelegramBot $telegramBot, BotMember $botMember, WalletService $wallet): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);
        abort_unless((int) $botMember->telegram_bot_id === (int) $telegramBot->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $wallet->topup($botMember, (int) $data['amount'], $data['note'] ?? 'Topup oleh owner');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Saldo '.$botMember->displayName().' ditambah Rp'.number_format($data['amount'], 0, ',', '.'));
    }

    protected function authorizeOwner(TelegramBot $bot): void
    {
        abort_unless(
            (int) $bot->user_id === (int) auth()->id() || auth()->user()?->is_admin,
            403
        );
    }
}
