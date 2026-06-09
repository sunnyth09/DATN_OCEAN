<?php

return [
    'mode' => env('TRYON_MODE', 'mock'), // 'mock' or 'live'
    'provider' => env('TRYON_PROVIDER', 'fashn'),
    'api_url' => env('TRYON_API_URL', 'https://api.fashn.ai/v1/run'),
    'status_url' => env('TRYON_STATUS_URL', 'https://api.fashn.ai/v1/status'),
    'api_key' => env('TRYON_API_KEY', ''),
    'timeout' => (int) env('TRYON_TIMEOUT_MS', 60000),
    'max_file_size_kb' => 5120, // 5MB
    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
];
