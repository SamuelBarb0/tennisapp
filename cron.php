<?php

/**
 * Cron entry point for shared hosts (Hostinger PHP cron mode) where the panel
 * only accepts a single PHP file path and cannot pass CLI args.
 *
 * What this does:
 *   1. Boots the Laravel application
 *   2. Resolves Artisan
 *   3. Runs `schedule:run` — same as `php artisan schedule:run`
 *
 * Configure this in Hostinger as:
 *   /usr/bin/php  /home/u864862219/domains/tennischallenge.com.co/tennisapp/cron.php
 */

define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('schedule:run');

$kernel->terminate(new Symfony\Component\Console\Input\ArrayInput([]), $status);
exit($status);
