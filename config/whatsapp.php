<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Meta Cloud API
    |--------------------------------------------------------------------------
    | Obtenez ces valeurs sur https://developers.facebook.com
    | → Mon appli → WhatsApp → Configuration API
    */
    'token'        => env('WHATSAPP_TOKEN'),
    'phone_id'     => env('WHATSAPP_PHONE_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'edupaypro_webhook_2025'),
    'api_url'      => 'https://graph.facebook.com/v19.0',

    // Limites Meta Cloud API gratuit : 1000 conversations/mois
    'free_tier_limit' => 1000,

    // Délai entre deux messages (ms) pour éviter le rate limiting
    'send_delay_ms' => 500,
];
