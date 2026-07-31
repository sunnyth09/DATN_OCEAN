<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Whitelist tất cả các origin hợp lệ
    'allowed_origins' => array_filter([
        env('URL_CORS', 'http://127.0.0.1:3302'),
        env('URL_CORS_LOCAL', 'http://localhost:3302'),
        env('FRONTEND_URL', 'https://oceansport.bcbdev.id.vn'),
        'https://oceansport.bcbdev.id.vn',
        'http://oceansport.bcbdev.id.vn',
        // Flutter Web dev server (localhost các port phổ biến)
        'http://localhost:5000',
        'http://localhost:5001',
        'http://localhost:8080',
        'http://127.0.0.1:5000',
        'http://127.0.0.1:5001',
        'http://127.0.0.1:8080',
        // Flutter Web trên Chrome debug
        'http://localhost',
        'http://127.0.0.1',
    ]),

    // Native Mobile App (Android/iOS) không gửi Origin header nên được phép luôn.
    // Flutter Web chạy từ file:// hoặc non-standard port cần pattern này.
    'allowed_origins_patterns' => ['#.*#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
