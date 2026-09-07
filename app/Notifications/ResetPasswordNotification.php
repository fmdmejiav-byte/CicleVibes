<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    /**
     * El token de restablecimiento generado por el broker de Laravel.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
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
        $expireMinutes = config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        $actionUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Restablece tu contraseña de CicleVibes')
            ->greeting('¡Hola '.($notifiable->nombre ?: 'usuario').'!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en **CicleVibes**.')
            ->line('Para continuar, pulsa el botón de abajo. Este enlace expira en **'.$expireMinutes.' minutos** y solo puede utilizarse una vez.')
            ->action('Restablecer contraseña', $actionUrl)
            ->line('Si no solicitaste este cambio, ignora este correo; tu contraseña seguirá siendo segura y no tendrás que hacer nada.')
            ->salutation('El equipo de CicleVibes');
    }
}
