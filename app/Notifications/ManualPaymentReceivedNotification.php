<?php

namespace App\Notifications;

use App\Models\ManualPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ManualPaymentReceivedNotification extends Notification
{
    use Queueable;

    protected $manualPayment;

    /**
     * Create a new notification instance.
     */
    public function __construct(ManualPayment $manualPayment)
    {
        $this->manualPayment = $manualPayment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->manualPayment->event;
        $amountFormatted = '$' . number_format($this->manualPayment->amount, 0, ',', '.');

        return (new MailMessage)
            ->subject('¡Nuevo abono registrado para tu evento!')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se ha registrado un nuevo abono manual para tu evento: **' . $event->name . '**.')
            ->line('Monto del abono: **' . $amountFormatted . '**')
            ->line('Descripción: ' . ($this->manualPayment->description ?: 'Abono manual registrado por administración.'))
            ->line('Este abono ha sido descontado automáticamente de la meta total de tu evento.')
            ->action('Ver mi Dashboard', url(config('app.frontend_url') . '/dashboard'))
            ->line('¡Gracias por usar Mi Regalo, Tu Fiesta!');
    }
}
