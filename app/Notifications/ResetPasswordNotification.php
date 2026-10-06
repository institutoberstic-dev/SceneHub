<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo de restablecimiento de contraseña en español.
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url)
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña de SceneHub')
            ->greeting('Hola,')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en SceneHub.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace vence en {$minutes} minutos y solo puede usarse una vez.")
            ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña actual seguirá funcionando.')
            ->salutation('Equipo SceneHub');
    }
}
