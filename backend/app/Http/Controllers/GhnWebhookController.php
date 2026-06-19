<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Log;

class GhnWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('GHN Webhook received', $request->all());

        $payload = $request->all();

        if (isset($payload['OrderCode']) && isset($payload['Status'])) {
            $order = Order::where('ghn_order_code', $payload['OrderCode'])->first();

            if ($order) {
                // Map GHN statuses to our system statuses
                $statusMapping = [
                    'ready_to_pick' => 'pending',
                    'picking' => 'shipping',
                    'cancel' => 'cancelled',
                    'money_collect_picking' => 'shipping',
                    'picked' => 'shipping',
                    'storing' => 'shipping',
                    'transporting' => 'shipping',
                    'sorting' => 'shipping',
                    'delivering' => 'shipping',
                    'money_collect_delivering' => 'shipping',
                    'delivered' => 'completed',
                    'delivery_fail' => 'shipping',
                    'waiting_to_return' => 'returned',
                    'return' => 'returned',
                    'return_transporting' => 'returned',
                    'return_sorting' => 'returned',
                    'returning' => 'returned',
                    'return_fail' => 'returned',
                    'returned' => 'returned',
                    'exception' => 'pending',
                    'damage' => 'cancelled',
                    'lost' => 'cancelled',
                ];

                if (array_key_exists($payload['Status'], $statusMapping)) {
                    $newStatus = $statusMapping[$payload['Status']];
                    
                    if ($order->fulfillment_status !== $newStatus) {
                        $oldStatus = $order->fulfillment_status;
                        $order->fulfillment_status = $newStatus;
                        
                        if ($newStatus === 'completed') {
                            $order->delivered_at = now();
                            $order->completed_at = now();
                        } elseif ($newStatus === 'cancelled') {
                            $order->cancelled_at = now();
                            $order->cancel_reason = 'Canceled by GHN';
                        }
                        
                        $order->save();

                        OrderStatusHistory::create([
                            'order_id' => $order->order_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'note' => 'Cập nhật tự động từ GHN (Webhook)',
                        ]);
                    }
                }
            }
        }

        return response()->json(['message' => 'Success'], 200);
    }
}
