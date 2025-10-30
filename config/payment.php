<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for payment processing systems.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | ArCoPay Acquiring Settings
    |--------------------------------------------------------------------------
    */

    'base_url' => env('PAYMENT_BASE_URL', 'https://api.arcopay.tech/api/v1'),
    'api_key' => env('PAYMENT_API_KEY'),
    'username' => env('PAYMENT_USERNAME'),
    'password' => env('PAYMENT_PASSWORD'),
    'public_key' => env('PAYMENT_PUBLIC_KEY'),
    'bearer_token' => env('PAYMENT_BEARER_TOKEN'), // Required for payment forms (IsForm: true)

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */

    'default_currency' => 'RUB',
    'qr_lifetime_minutes' => 15,
    'form_lifetime_minutes' => 30, // Payment form lifetime in minutes
    'payment_check_interval_minutes' => 1,

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    */
    'success_url' => env('PAYMENT_SUCCESS_URL', '/profile#balance?payment=success'),
    'fail_url' => env('PAYMENT_FAIL_URL', '/profile#balance?payment=failed'),
];