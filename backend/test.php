<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = \App\Models\Order::first();
if ($order) {
    echo "Using Order: " . $order->order_id . " Status: " . $order->fulfillment_status . " Payment: " . $order->payment_method . "\n";
    $svc = app(\App\Services\AdminOrderService::class);
    
    // Force it to shipping and wallet payment
    $order->update(['fulfillment_status' => 'pending', 'payment_method' => 'wallet', 'payment_status' => 'paid']);
    echo "Forced to pending & wallet paid\n";
    
    try {
        $res = $svc->updateStatus($order->order_id, ["fulfillment_status" => "cancelled"]);
        echo "Result: " . json_encode($res) . "\n";
    } catch (\Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "No order found\n";
}
