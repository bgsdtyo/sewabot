<?php

namespace App\Http\Controllers;

use App\Models\BotMember;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotDetailController extends Controller
{
    public function show(TelegramBot $telegramBot): View
    {
        $this->authorizeOwner($telegramBot);

        $telegramBot->load('product');
        $services = OtpService::sellable()->orderBy('name')->get();

        return view('bots.show', compact('telegramBot', 'services'));
    }

    public function updateSettings(Request $request, TelegramBot $telegramBot): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);

        $data = $request->validate([
            'otp_api_key' => ['nullable', 'string', 'max:500'],
            'otp_markup_type' => ['required', 'in:percent,flat'],
            'otp_markup_percent' => ['required', 'integer', 'min:0', 'max:1000000'],
            'deposit_whatsapp' => ['nullable', 'string', 'max:100'],
            'deposit_telegram' => ['nullable', 'string', 'max:100'],
            'deposit_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = [
            'otp_markup_type' => $data['otp_markup_type'],
            'otp_markup_percent' => (int) $data['otp_markup_percent'],
            'deposit_whatsapp' => filled($data['deposit_whatsapp'] ?? null) ? trim($data['deposit_whatsapp']) : null,
            'deposit_telegram' => filled($data['deposit_telegram'] ?? null) ? trim($data['deposit_telegram']) : null,
            'deposit_note' => filled($data['deposit_note'] ?? null) ? trim($data['deposit_note']) : null,
        ];

        if ($request->boolean('clear_api_key')) {
            $updates['otp_api_key'] = null;
        } elseif (filled($data['otp_api_key'] ?? null)) {
            $updates['otp_api_key'] = trim($data['otp_api_key']);
        }

        $telegramBot->update($updates);

        return back()->with('success', 'Konfigurasi bot disimpan.');
    }

    public function syncServices(TelegramBot $telegramBot, OtpOrderService $otp): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);

        try {
            $count = $otp->syncServices(['KOPKEN'], $telegramBot);

            return back()->with('success', "Sync KOPKEN berhasil ({$count} layanan) memakai API key bot ini.");
        } catch (\Throwable $e) {
            return back()->withErrors(['otp_api_key' => $e->getMessage()]);
        }
    }

    public function topup(Request $request, TelegramBot $telegramBot, BotMember $member, WalletService $wallet): RedirectResponse
    {
        $this->authorizeOwner($telegramBot);
        abort_unless($member->telegram_bot_id === $telegramBot->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $wallet->topup($member, (int) $data['amount'], $data['note'] ?? 'Topup oleh owner');

        return redirect()
            ->route('dashboard', ['members' => $request->query('members')])
            ->with('success', 'Saldo '.$member->displayName().' ditambah Rp'.number_format($data['amount'], 0, ',', '.'));
    }

    protected function authorizeOwner(TelegramBot $bot): void
    {
        abort_unless($bot->user_id === auth()->id() || auth()->user()->is_admin, 403);
    }
}
