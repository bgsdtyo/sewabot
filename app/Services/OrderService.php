<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected TelegramBotService $telegramBotService
    ) {}

    /**
     * @param  array{bot_name: string, bot_notes?: string|null, periods: int}  $config
     */
    public function createNewSubscriptionOrder(User $user, Product $product, array $config): Order
    {
        $pending = $user->orders()
            ->where('type', 'new_subscription')
            ->whereIn('status', ['pending'])
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'order' => 'Anda masih memiliki pembayaran yang menunggu konfirmasi.',
            ]);
        }

        if ($user->activeSubscription()) {
            throw ValidationException::withMessages([
                'order' => 'Anda sudah memiliki subscription aktif. Gunakan fitur perpanjang.',
            ]);
        }

        $periods = max(1, (int) ($config['periods'] ?? 1));
        $durationDays = $product->daysForPeriods($periods);
        $amount = $product->priceForPeriods($periods, false);
        $botName = trim($config['bot_name']);
        $botNotes = trim((string) ($config['bot_notes'] ?? '')) ?: null;

        return DB::transaction(function () use ($user, $product, $periods, $durationDays, $amount, $botName, $botNotes) {
            $bot = TelegramBot::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'name' => $botName,
                'username' => null,
                'status' => 'assigned',
                'notes' => $botNotes,
            ]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'telegram_bot_id' => $bot->id,
                'status' => 'pending',
            ]);

            return Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'subscription_id' => $subscription->id,
                'telegram_bot_id' => $bot->id,
                'invoice_number' => Order::generateInvoiceNumber(),
                'type' => 'new_subscription',
                'amount' => $amount,
                'duration_days' => $durationDays,
                'periods' => $periods,
                'status' => 'pending',
            ]);
        });
    }

    public function createRenewalOrder(User $user, Subscription $subscription, int $periods = 1): Order
    {
        if ($subscription->user_id !== $user->id) {
            abort(403);
        }

        $pending = $user->orders()
            ->where('type', 'renewal')
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'order' => 'Anda masih memiliki perpanjangan yang menunggu konfirmasi.',
            ]);
        }

        $periods = max(1, $periods);
        $product = $subscription->product;

        return Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'subscription_id' => $subscription->id,
            'telegram_bot_id' => $subscription->telegram_bot_id,
            'invoice_number' => Order::generateInvoiceNumber(),
            'type' => 'renewal',
            'amount' => $product->priceForPeriods($periods, true),
            'duration_days' => $product->daysForPeriods($periods),
            'periods' => $periods,
            'status' => 'pending',
        ]);
    }

    public function uploadPaymentProof(Order $order, UploadedFile $file): Order
    {
        if (! in_array($order->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Bukti pembayaran tidak dapat diunggah untuk status ini.',
            ]);
        }

        $path = $file->store('payment-proofs', 'public');

        $order->update([
            'payment_proof' => $path,
            'status' => 'pending',
            'admin_note' => null,
        ]);

        return $order->fresh();
    }

    public function approve(Order $order, ?string $adminNote = null): Order
    {
        if ($order->status === 'paid') {
            return $order;
        }

        return DB::transaction(function () use ($order, $adminNote) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'admin_note' => $adminNote,
            ]);

            $subscription = $order->subscription;
            $days = $order->duration_days ?: ($order->product->duration_days ?? 30);

            if ($order->type === 'new_subscription') {
                $subscription->update([
                    'status' => 'active',
                    'started_at' => now(),
                    'expires_at' => now()->addDays($days),
                ]);
            } else {
                $base = ($subscription->status === 'active' && $subscription->expires_at && $subscription->expires_at->isFuture())
                    ? $subscription->expires_at
                    : now();

                $subscription->update([
                    'status' => 'active',
                    'started_at' => $subscription->started_at ?? now(),
                    'expires_at' => $base->copy()->addDays($days),
                ]);
            }

            if ($subscription->telegramBot) {
                $this->telegramBotService->activate($subscription->telegramBot);
            }

            $order->user->notify(new PaymentApprovedNotification($order->fresh(['product', 'subscription.telegramBot'])));

            return $order->fresh(['user', 'product', 'subscription.telegramBot']);
        });
    }

    public function reject(Order $order, ?string $adminNote = null): Order
    {
        $order->update([
            'status' => 'rejected',
            'admin_note' => $adminNote ?? 'Bukti pembayaran ditolak. Silakan upload ulang.',
        ]);

        return $order->fresh();
    }
}
