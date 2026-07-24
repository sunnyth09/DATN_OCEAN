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

    // Chỉ cho phép frontend URL — thêm production URL khi deploy
    'allowed_origins' => array_filter([
        env('URL_CORS', 'http://127.0.0.1:3302'),
        env('URL_CORS_LOCAL', 'http://localhost:3302'),
    ]),

    // KHÔNG dùng pattern '*' — nó match mọi origin, vô hiệu hoá whitelist ở trên
    // và kết hợp supports_credentials=true là lỗ hổng CORS nghiêm trọng.
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
