<?php
$credentials = ['email' => 'admin@ocean.com', 'password' => 'password123'];
$token = auth('admin')->attempt($credentials);
echo "Token: " . ($token ? "Yes" : "No") . "\n";
if (!$token) {
    // try api guard
    $token2 = auth('api')->attempt($credentials);
    echo "API Token: " . ($token2 ? "Yes" : "No") . "\n";
}
