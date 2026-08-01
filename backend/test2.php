<?php

use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$order = Order::where('order_code', 'ORD6A5A76E14C68819')->first();
$rule = LoyaltyRule::findByKey('ORDER_COMPLETE');
$alreadyEarned = LoyaltyTransaction::forUser($order->user_id)->where('rule_id', $rule->id)->where('reference_type', Order::class)->where('reference_id', $order->order_id)->exists();
echo json_encode(['order_id' => $order->order_id, 'grand_total' => $order->grand_total, 'rule_id' => $rule->id, 'alreadyEarned' => $alreadyEarned]);
