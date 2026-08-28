<?php

namespace App\StateMachines;

use App\Models\Order;

class OrderStateMachine
{
    /**
     * Map current_status => next_status => allowed_actors
     * Actors: admin, user, system, carrier, cron
     */
    private const TRANSITIONS = [
        'pending' => [
            'confirmed' => ['admin', 'system'],
            'cancelled' => ['admin', 'user', 'system'],
        ],
        'confirmed' => [
            'processing' => ['admin', 'system'],
            'packing' => ['admin', 'system'],
            'cancelled' => ['admin', 'user', 'system'],
        ],
        'processing' => [
            'packing' => ['admin', 'system'],
            'awaiting_pickup' => ['system', 'carrier'],
            'cancelled' => ['admin', 'system'],
        ],
        'packing' => [
            'awaiting_pickup' => ['system', 'carrier'],
            'cancelled' => ['admin', 'system'],
        ],
        'awaiting_pickup' => [
            'shipping' => ['carrier', 'system'],
            'cancelled' => ['admin', 'system', 'carrier'],
        ],
        'shipping' => [
            'delivered' => ['carrier', 'system'],
            'cancelled' => ['carrier', 'system'],
            'returning' => ['carrier'],
            'return_requested' => ['admin', 'system'],
        ],
        'delivered' => [
            'completed' => ['admin', 'system', 'cron', 'user'],
            'return_requested' => ['admin', 'system', 'user'],
        ],
        'completed' => [
            'return_requested' => ['admin', 'system'],
        ],
        'cancelled' => [],
        'return_requested' => [
            'return_approved' => ['admin', 'system'],
            'return_rejected' => ['admin', 'system'],
        ],
        'return_approved' => [
            'returning' => ['user', 'carrier', 'system'],
        ],
        'returning' => [
            'warehouse_received' => ['carrier', 'system', 'admin'],
        ],
        'warehouse_received' => [
            'inspected_ok' => ['admin', 'system'],
            'inspection_failed' => ['admin', 'system'],
        ],
        'inspected_ok' => [
            'returned' => ['admin', 'system'],
        ],
        'inspection_failed' => [
            'return_rejected' => ['admin', 'system'],
        ],
        'return_rejected' => [],
        'returned' => [
            'refunded' => ['admin', 'system'],
        ],
        'refunded' => [],
    ];

    /**
     * Trạng thái thuộc sở hữu của hãng vận chuyển: chỉ webhook / hệ thống tạo vận đơn mới được cập nhật.
     */
    private const CARRIER_OWNED = ['shipping', 'delivered', 'returning'];

    /**
     * Check if transition is allowed
     */
    public static function canTransition(Order $order, string $newStatus, string $actor = 'admin'): bool
    {
        $current = $order->fulfillment_status;

        if ($current === $newStatus) {
            return false;
        }

        // Nếu admin thao tác trên đơn đã có mã vận đơn đối tác thì không được chỉnh trạng thái vận chuyển
        if ($actor === 'admin' && in_array($newStatus, self::CARRIER_OWNED, true)) {
            return false;
        }

        if ($actor === 'admin' && ! empty($order->tracking_number) && $order->tracking_number !== 'SELF-DELIVERY') {
            if (in_array($newStatus, ['pending', 'confirmed', 'processing', 'packing', 'awaiting_pickup', 'shipping', 'delivered', 'returning', 'returned'], true)) {
                return false;
            }
        }

        $allowedActors = self::TRANSITIONS[$current][$newStatus] ?? null;

        if ($allowedActors === null) {
            return false;
        }

        return in_array($actor, $allowedActors, true);
    }

    /**
     * Get available transitions for an actor
     */
    public static function getAvailableTransitions(Order $order, string $actor = 'admin'): array
    {
        $current = $order->fulfillment_status;
        $possible = self::TRANSITIONS[$current] ?? [];

        $available = [];
        foreach ($possible as $nextStatus => $actors) {
            if (in_array($actor, $actors, true)) {
                if (self::canTransition($order, $nextStatus, $actor)) {
                    $available[] = $nextStatus;
                }
            }
        }

        return $available;
    }
}
