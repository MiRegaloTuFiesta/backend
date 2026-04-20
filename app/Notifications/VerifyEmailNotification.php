<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(Lang::get('Verifica tu correo electrónico - Mi Regalo, Tu Fiesta'))
            ->greeting('¡Hola!')
            ->line(Lang::get('Haz clic en el botón de abajo para verificar tu dirección de correo electrónico.'))
            ->action(Lang::get('Verificar Correo'), $verificationUrl)
            ->line(Lang::get('Si no creaste una cuenta, no es necesario realizar ninguna otra acción.'))
            ->salutation('Saludos, el equipo de Mi Regalo, Tu Fiesta');
    }

    protected function verificationUrl($notifiable)
    {
        // Generate the signed backend URL
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Wrap it in a frontend URL so the user sees a frontend link
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return $frontendUrl . '/verify-email?verify_url=' . urlencode($backendUrl);
    }
}
