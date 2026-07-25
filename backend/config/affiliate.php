<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Commission Rate (%)
    |--------------------------------------------------------------------------
    | Tỷ lệ hoa hồng mặc định cho mỗi đơn hàng affiliate.
    */
    'commission_rate' => 5,

    /*
    |--------------------------------------------------------------------------
    | Cookie / LocalStorage Days
    |--------------------------------------------------------------------------
    | Thời gian lưu referral code trong cookie/localStorage (ngày).
    */
    'cookie_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Minimum Withdrawal Amount (VND)
    |--------------------------------------------------------------------------
    | Số tiền tối thiểu để gửi yêu cầu rút hoa hồng.
    */
    'min_withdraw_amount' => 100000,

    /*
    |--------------------------------------------------------------------------
    | Affiliate Spam Protection
    |--------------------------------------------------------------------------
    | Các ngưỡng chống spam cho affiliate tracking. Giữ ở config để dễ tinh
    | chỉnh theo traffic thực tế mà không phải sửa logic service/controller.
    */
    'spam_protection' => [
        'click_dedup_hours' => 24,

        'track_click' => [
            'ip_per_minute' => 30,
            'ip_per_hour' => 300,
            'code_per_minute' => 120,
            'code_per_hour' => 1000,
            'user_agent_max_length' => 500,
        ],

        'authenticated' => [
            'read_per_minute' => 60,
            'register_per_hour' => 3,
            'withdrawal_per_hour' => 3,
        ],
    ],
];
