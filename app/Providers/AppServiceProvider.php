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

        // Use our branded Tennis Challenge mail layout (resources/views/emails/auth/*)
        // for the password-reset and email-verification notifications, instead of
        // Laravel's default "Hello! Regards" template that ships with the framework.
        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

            return (new MailMessage)
                ->subject('Restablece tu contraseña — Tennis Challenge')
                ->view('emails.auth.reset-password', [
                    'user'   => $notifiable,
                    'url'    => $url,
                    'expire' => $expire,
                ]);
        });

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Verifica tu correo electrónico — Tennis Challenge')
                ->view('emails.auth.verify-email', [
                    'user' => $notifiable,
                    'url'  => $url,
                ]);
        });
    }
}
