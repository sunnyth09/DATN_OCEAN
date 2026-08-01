<?php

/**
 * VNPay Payment Gateway Configuration
 *
 * Tách cấu hình VNPay ra file config riêng để:
 * 1. env() chỉ gọi trong file config (đúng best practice Laravel)
 * 2. Hoạt động đúng khi chạy php artisan config:cache (production)
 * 3. Dễ override trong testing bằng Config::set()
 */
return [
    'tmn_code' => env('VNP_TMN_CODE', ''),
    'hash_secret' => env('VNP_HASH_SECRET', ''),
    'url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'return_url' => env('VNP_RETURN_URL', ''),
    'ipn_url' => env('VNP_IPN_URL', ''),   // Server-to-server callback URL
];
