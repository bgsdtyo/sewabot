<?php

namespace App\Services;

use App\Models\BotMember;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function topup(BotMember $member, int $amount, ?string $note = null): BotMember
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal topup harus lebih dari 0.']);
        }

        return DB::transaction(function () use ($member, $amount, $note) {
            $member = BotMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $member->balance += $amount;
            $member->save();

            WalletTransaction::create([
                'bot_member_id' => $member->id,
                'telegram_bot_id' => $member->telegram_bot_id,
                'type' => 'topup',
                'amount' => $amount,
                'balance_after' => $member->balance,
                'note' => $note ?? 'Topup saldo',
            ]);

            return $member->fresh();
        });
    }

    public function hold(BotMember $member, int $amount, string $referenceType, int $referenceId, ?string $note = null): BotMember
    {
        return DB::transaction(function () use ($member, $amount, $referenceType, $referenceId, $note) {
            $member = BotMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($member->availableBalance() < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Saldo tidak cukup. Tersedia '.$member->formattedAvailable().', dibutuhkan Rp'.number_format($amount, 0, ',', '.'),
                ]);
            }

            $member->held_balance += $amount;
            $member->save();

            WalletTransaction::create([
                'bot_member_id' => $member->id,
                'telegram_bot_id' => $member->telegram_bot_id,
                'type' => 'hold',
                'amount' => $amount,
                'balance_after' => $member->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note ?? 'Hold saldo OTP',
            ]);

            return $member->fresh();
        });
    }

    public function chargeHeld(BotMember $member, int $amount, string $referenceType, int $referenceId, ?string $note = null): BotMember
    {
        return DB::transaction(function () use ($member, $amount, $referenceType, $referenceId, $note) {
            $member = BotMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            $member->held_balance = max(0, $member->held_balance - $amount);
            $member->balance = max(0, $member->balance - $amount);
            $member->save();

            WalletTransaction::create([
                'bot_member_id' => $member->id,
                'telegram_bot_id' => $member->telegram_bot_id,
                'type' => 'charge',
                'amount' => -$amount,
                'balance_after' => $member->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note ?? 'Potong saldo OTP masuk',
            ]);

            return $member->fresh();
        });
    }

    public function releaseHold(BotMember $member, int $amount, string $referenceType, int $referenceId, ?string $note = null): BotMember
    {
        return DB::transaction(function () use ($member, $amount, $referenceType, $referenceId, $note) {
            $member = BotMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            $member->held_balance = max(0, $member->held_balance - $amount);
            $member->save();

            WalletTransaction::create([
                'bot_member_id' => $member->id,
                'telegram_bot_id' => $member->telegram_bot_id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_after' => $member->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note ?? 'Refund hold OTP dibatalkan',
            ]);

            return $member->fresh();
        });
    }
}
