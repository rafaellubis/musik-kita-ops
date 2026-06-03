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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Wablas — WhatsApp gateway (reminder tagihan ke ortu).
    | Authorization header: {token}.{secret_key}
    */
    'wablas' => [
        'token'      => env('WABLAS_TOKEN'),
        'secret_key' => env('WABLAS_SECRET_KEY'),
        'base_url'   => env('WABLAS_BASE_URL', 'https://solo.wablas.com'),
    ],

    /*
    | Fonnte — WhatsApp gateway (pengingat jadwal ke ortu).
    | Authorization header: {token} (tanpa Bearer).
    */
    'fonnte' => [
        'token'        => env('FONNTE_TOKEN'),
        'base_url'     => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'country_code' => env('FONNTE_COUNTRY_CODE', '62'),
    ],

];
