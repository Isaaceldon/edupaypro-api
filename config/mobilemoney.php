<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile Money — Configuration opérateurs
    |--------------------------------------------------------------------------
    */

    'default' => env('MOBILE_MONEY_DEFAULT', 'mtn'),

    'mtn' => [
        'base_url'         => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
        'api_user'         => env('MTN_MOMO_API_USER'),
        'api_key'          => env('MTN_MOMO_API_KEY'),
        'target_env'       => env('MTN_MOMO_TARGET_ENV', 'sandbox'),
        'currency'         => 'XOF',
    ],

    'moov' => [
        'base_url'    => env('MOOV_BASE_URL', 'https://api.moov.africa'),
        'merchant_id' => env('MOOV_MERCHANT_ID'),
        'secret'      => env('MOOV_SECRET'),
        'currency'    => 'XOF',
    ],

    // Timeout webhook (secondes) — après ce délai, le paiement passe en "pending"
    'webhook_timeout' => 300,
];
