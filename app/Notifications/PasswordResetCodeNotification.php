<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    public const EXPIRES_IN_MINUTES = 10;

    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu código de seguridad de CicleVibes')
            ->greeting('¡Hola '.($notifiable->nombre ?: 'usuario').'!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en **CicleVibes**.')
            ->line('Usa este código de **6 dígitos** para continuar:')
            ->line('**'.$this->code.'**')
            ->line('El código expira en **'.self::EXPIRES_IN_MINUTES.' minutos**. Si no lo usas a tiempo, solicita uno nuevo.')
            ->line('**No compartas este código con nadie.** El equipo de CicleVibes jamás te pedirá tu código de seguridad.')
            ->line('Si no solicitaste este cambio, ignora este correo; tu contraseña seguirá siendo segura y no tendrás que hacer nada.')
            ->salutation('El equipo de CicleVibes');
    }
}