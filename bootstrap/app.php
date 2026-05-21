<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Mercado Pago IPN can't carry our CSRF token
        $middleware->validateCsrfTokens(except: [
            'payments/mp/webhook',
        ]);

        // Trust X-Forwarded-* headers from any proxy. This is what makes
        // route() generate https:// URLs when we're behind ngrok / Cloudflare
        // / a Hostinger load balancer that terminates SSL upstream. Without
        // this, route() falls back to http:// even if APP_URL is https://,
        // which Mercado Pago rejects with "back_url.success must be defined".
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
