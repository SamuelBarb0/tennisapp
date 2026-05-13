<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sportradar' => [
        'key' => env('SPORTRADAR_API_KEY'),
        'base_url' => env('SPORTRADAR_BASE_URL', 'https://api.sportradar.com/tennis/production/v3/en/'),
    ],

    'mercadopago' => [
        'public_key'   => env('MERCADOPAGO_PUBLIC_KEY'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'currency'     => env('MERCADOPAGO_CURRENCY', 'COP'),
    ],

    'matchstat' => [
        'key'  => env('MATCHSTAT_API_KEY'),
        'host' => env('MATCHSTAT_API_HOST', 'tennis-api-atp-wta-itf.p.rapidapi.com'),
        'base' => env('MATCHSTAT_API_BASE', 'https://tennis-api-atp-wta-itf.p.rapidapi.com'),
    ],

    // api-tennis.com — primary tennis data provider (replaces Matchstat).
    // Provides full tournament list, ATP/WTA rankings, fixtures with player photos.
    'api_tennis' => [
        'key'  => env('API_TENNIS_KEY'),
        'base' => env('API_TENNIS_BASE', 'https://api.api-tennis.com/tennis/'),
    ],

];
