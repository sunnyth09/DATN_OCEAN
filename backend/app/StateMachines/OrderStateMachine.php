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
            'awaiting_pickup' => ['admin', 'system', 'carrier'],
            'shipping' => ['admin', 'system', 'carrier'],
            'cancelled' => ['admin', 'system'],
        ],
        'packing' => [
            'awaiting_pickup' => ['admin', 'system', 'carrier'],
            'shipping' => ['admin', 'system', 'carrier'],
            'cancelled' => ['admin', 'system'],
        ],
        'awaiting_pickup' => [
            'shipping' => ['admin', 'system', 'carrier'],
            'cancelled' => ['admin', 'system'],
        ],
        'shipping' => [
            'delivered' => ['admin', 'system', 'carrier'],
            'cancelled' => ['admin', 'system'],
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
            'returning' => ['admin', 'system', 'user', 'carrier'],
        ],
        'returning' => [
            'warehouse_received' => ['admin', 'system', 'carrier'],
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
     * Check if transition is allowed
     */
    public static function canTransition(Order $order, string $newStatus, string $actor = 'admin'): bool
    {
        $current = $order->fulfillment_status;

        if ($current === $newStatus) {
            return false;
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
                $available[] = $nextStatus;
            }
        }

        return $available;
    }
}
