<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Order $order,
        protected string $eventType, // e.g. 'order_paid', 'order_shipped'
        protected string $customTitle,
        protected string $customBody
    ) {}

    public function via(object $notifiable): array
    {
        // For Sprint 9: Some events send email + DB, some only DB.
        $dbOnlyEvents = ['order_delivered', 'return_received'];
        $emailOnlyEvents = ['abandoned_cart'];

        if (in_array($this->eventType, $dbOnlyEvents)) {
            return ['database'];
        }

        if (in_array($this->eventType, $emailOnlyEvents)) {
            return ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->customTitle)
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line($this->customBody)
            ->action('Lihat Detail Pesanan', route('orders.show', $this->order->order_number))
            ->line('Terima kasih telah berbelanja di toko kami!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => $this->eventType,
            'title'    => $this->customTitle,
            'body'     => $this->customBody,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ];
    }
}
