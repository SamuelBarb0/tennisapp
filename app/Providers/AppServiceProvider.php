<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Render dates/times produced by Carbon (diffForHumans, formatLocalized, etc.)
        // in Spanish. Without this the locale stays at "en" even though APP_LOCALE=es.
        Carbon::setLocale(config('app.locale', 'es'));

        // The default password-reset notification ships in English. The MailMessage
        // strings are sent through __(), but the SUBJECT line and a couple of
        // composed sentences aren't, so we rebuild the message in Spanish.
        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

            return (new MailMessage)
                ->subject('Restablece tu contraseña — Tennis Challenge')
                ->greeting('¡Hola!')
                ->line('Recibes este correo porque solicitaste restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace expira en {$expire} minutos.")
                ->line('Si no solicitaste el restablecimiento, puedes ignorar este correo.')
                ->salutation('Saludos, el equipo de Tennis Challenge');
        });

        // Same treatment for the email-verification notification.
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verifica tu correo electrónico — Tennis Challenge')
                ->greeting('¡Hola!')
                ->line('Gracias por registrarte en Tennis Challenge. Por favor verifica tu dirección de correo electrónico haciendo clic en el botón de abajo.')
                ->action('Verificar correo', $url)
                ->line('Si no creaste una cuenta, puedes ignorar este correo.')
                ->salutation('Saludos, el equipo de Tennis Challenge');
        });
    }
}
