<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ocean Express API
    |--------------------------------------------------------------------------
    |
    | Toàn bộ cấu hình hãng vận chuyển Ocean Express. KHÔNG gọi env() trực tiếp
    | trong service — khi production chạy `php artisan config:cache`, env() trả
    | về null và mọi request tới hãng vận chuyển sẽ bay vào URL rỗng.
    |
    */
    'api_url' => rtrim(env('OCEAN_EXPRESS_API_URL', 'https://api.oceanexpress.bcbdev.id.vn/api/v1'), '/'),
    'api_key' => env('OCEAN_EXPRESS_API_KEY'),
    'timeout' => (int) env('OCEAN_EXPRESS_TIMEOUT', 10),

    // Phí fallback khi API tính phí không phản hồi (VND)
    'fallback_fee' => (int) env('OCEAN_EXPRESS_FALLBACK_FEE', 30000),

    // Trọng lượng mặc định mỗi sản phẩm khi product.weight chưa được nhập (gram)
    'default_weight' => (int) env('OCEAN_EXPRESS_DEFAULT_WEIGHT', 500),
    'min_weight' => (int) env('OCEAN_EXPRESS_MIN_WEIGHT', 100),

    /*
    |--------------------------------------------------------------------------
    | Bảo mật Webhook
    |--------------------------------------------------------------------------
    |
    | Webhook là kênh DUY NHẤT được phép đẩy đơn hàng sang các trạng thái giao
    | vận (shipping/delivered/returning) và nó kích hoạt hoàn tiền ví + hoàn tồn
    | kho. Vì vậy bắt buộc phải xác thực. tracking_number được in trên nhãn dán
    | ngoài thùng hàng nên KHÔNG được coi là secret.
    |
    | - webhook_token: shared secret, gửi qua ?token=... hoặc header X-Webhook-Token
    | - webhook_secret: HMAC-SHA256 ký raw body, gửi qua header X-Signature
    | - webhook_allowed_ips: lớp phòng thủ bổ sung (CSV)
    |
    | require_auth = true (mặc định) sẽ TỪ CHỐI mọi webhook nếu chưa cấu hình
    | token/secret — fail-closed, tránh việc thiếu env biến endpoint thành công khai.
    |
    */
    'webhook_token' => env('OCEAN_EXPRESS_WEBHOOK_TOKEN'),
    'webhook_secret' => env('OCEAN_EXPRESS_WEBHOOK_SECRET'),
    'webhook_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OCEAN_EXPRESS_WEBHOOK_ALLOWED_IPS', ''))
    ))),
    'webhook_require_auth' => filter_var(
        env('OCEAN_EXPRESS_WEBHOOK_REQUIRE_AUTH', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'tracking_url' => env('OCEAN_EXPRESS_TRACKING_URL', 'https://oceanexpress.bcbdev.id.vn/tracking'),
];
