<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $bot = $this->order->subscription?->telegramBot?->displayUsername() ?? 'Telegram Bot';

        return [
            'title' => 'Pembayaran dikonfirmasi',
            'message' => 'Pembayaran berhasil dikonfirmasi. Telegram Bot kamu sekarang sudah aktif. ('.$bot.')',
            'order_id' => $this->order->id,
            'invoice_number' => $this->order->invoice_number,
        ];
    }
}
