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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'steam' => [
        'client_id' => null,
        'client_secret' => env('STEAM_API_KEY'),
        'redirect' => env('STEAM_REDIRECT_URL'),
        'allowed_hosts' => [
            parse_url(env('APP_URL'), PHP_URL_HOST),
        ],
        'force_https' => true,
    ],

    'bitskins' => [
        'auth_token' => env('BITSKINS_AUTH_TOKEN'),
        'api_base_url' => env('BITSKINS_API_BASE_URL', 'https://api.bitskins.com'),
        'image_base_url' => env('BITSKINS_IMAGE_BASE_URL', 'https://ss.bitskins.com'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_name' => env('TELEGRAM_BOT_NAME'),
    ],

    'losreferidos' => [
        'adv_id' => env('LR_ADV_ID'),
        'hash' => env('LR_HASH'),
        'base_url' => env('LR_BASE_URL'),
    ],

    'partner_api' => [
        'secret' => env('PARTNER_API_SECRET'),
    ],

];
