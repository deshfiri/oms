<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

class PathaoCourier implements CourierAdapter
{
    public function slug(): string        { return 'pathao'; }
    public function displayName(): string { return 'Pathao'; }

    public function capabilities(): array
    {
        // Pathao gives a real consignment id + a public tracking page, but the
        // OMS books it THROUGH the store website's courier API (OAuth lives
        // there). No direct OMS booking.
        return ['provides_consignment'=>true, 'provides_tracking'=>true, 'direct_api'=>false, 'manual_entry'=>false];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        // No direct OMS integration — Pathao is booked via the store website's
        // courier API. We never fabricate an id.
        throw new CourierUnavailableException('Pathao is booked through the store website — no direct OMS booking.');
    }

    public function normalizeStatus(string $native): string
    {
        return match (strtolower($native)) {
            'pending'              => 'booked',
            'pickup_requested'     => 'booked',
            'assigned_for_pickup'  => 'picked_up',
            'picked'               => 'picked_up',
            'pick_failed'          => 'delivery_failed',
            'pickup_cancelled'     => 'delivery_failed',
            'at_the_sorting_hub'   => 'hub_received',
            'in_transit'           => 'in_transit',
            'assigned_for_delivery'=> 'out_for_delivery',
            'delivered'            => 'delivered',
            'delivery_failed'      => 'delivery_failed',
            'on_hold'              => 'in_transit',
            'return'               => 'returned',
            default                => $native,
        };
    }
}
