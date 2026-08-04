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

    // Custom SMTP credentials cho gửi email đơn hàng
    'email' => [
        'username' => env('EMAIL_USER'),
        'password' => env('EMAIL_PASS'),
    ],

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL', 'http://localhost:3302/api/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_ID'),
        'client_secret' => env('FACEBOOK_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT', 'http://localhost:3302/api/auth/facebook/callback'),
    ],

    'store' => [
        'wifi_ip' => env('STORE_WIFI_IP'),
        'lat' => env('STORE_LAT'),
        'lng' => env('STORE_LNG'),
    ],

    'sepay' => [
        'api_key' => env('SEPAY_API_KEY'),
    ],

    'bank' => [
        'bin' => env('BANK_BIN'),
        'account_number' => env('BANK_ACCOUNT_NUMBER'),
        'account_name' => env('BANK_ACCOUNT_NAME'),
    ],

    // Gemini API keys — nhiều key để rotate khi bị rate limit (backup)
    'gemini' => [
        'keys' => array_values(array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
            env('GEMINI_API_KEY_4'),
        ])),
    ],

    // DeepSeek API — chuẩn OpenAI-compatible
    'deepseek' => [
        'api_key'  => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model'    => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    // Face Verification Microservice (internal Docker network)
    'face' => [
        'url' => env('FACE_SERVICE_URL', 'http://face-service:8001'),
        'timeout' => env('FACE_SERVICE_TIMEOUT', 15),
    ],

];
