<?php

return [
    'token' => env('GHN_TOKEN'),
    'shop_id' => (int) env('GHN_SHOP_ID'),
    'base_url' => rtrim(env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn'), '/'),
    'timeout' => (int) env('GHN_TIMEOUT', 10),

    'service_type_id' => (int) env('GHN_SERVICE_TYPE_ID', 2),
    'required_note' => env('GHN_REQUIRED_NOTE', 'KHONGCHOXEMHANG'),
    'default_weight' => (int) env('GHN_DEFAULT_WEIGHT', 500),
    'min_weight' => (int) env('GHN_MIN_WEIGHT', 10),

    'sender' => [
        'name' => env('GHN_SENDER_NAME', 'OCEAN SHOP'),
        'phone' => env('GHN_SENDER_PHONE'),
        'address' => env('GHN_SENDER_ADDRESS'),
        'ward_code' => env('GHN_SENDER_WARD_CODE'),
        'district_id' => (int) env('GHN_SENDER_DISTRICT_ID'),
    ],

    'webhook_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('GHN_WEBHOOK_ALLOWED_IPS', ''))
    ))),

    // Shared secret gắn vào URL webhook GHN (?token=... hoặc header X-Webhook-Token)
    'webhook_token' => env('GHN_WEBHOOK_TOKEN'),

    'tracking_url' => env('GHN_TRACKING_URL', 'https://donhang.ghn.vn'),
];
