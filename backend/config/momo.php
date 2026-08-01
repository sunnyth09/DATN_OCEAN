<?php

return [

    // MoMo config
    'partner_code' => env('MOMO_PARTNER_CODE'),
    'access_key' => env('MOMO_ACCESS_KEY'),
    'secret_key' => env('MOMO_SECRET_KEY'),
    'endpoint' => env('MOMO_ENDPOINT'),

    // URL callback
    'redirect_url' => env('MOMO_REDIRECT_URL'),
    'ipn_url' => env('MOMO_IPN_URL'),

];
