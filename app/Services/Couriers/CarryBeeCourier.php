<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

class CarryBeeCourier implements CourierAdapter
{
    public function slug(): string        { return 'carrybee'; }
    public function displayName(): string { return 'CarryBee'; }

    public function capabilities(): array
    {
        return ['provides_consignment'=>true, 'provides_tracking'=>true, 'direct_api'=>false, 'manual_entry'=>false];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        throw new CourierUnavailableException('CarryBee is booked through the store website — no direct OMS booking.');
    }

    public function normalizeStatus(string $native): string
    {
        return match (strtolower($native)) {
            'booked','pending'   => 'booked',
            'picked','picked_up' => 'picked_up',
            'in_transit'         => 'in_transit',
            'hub_received'       => 'hub_received',
            'out_for_delivery'   => 'out_for_delivery',
            'delivered'          => 'delivered',
            'failed','exception' => 'delivery_failed',
            'returned'           => 'returned',
            default              => $native,
        };
    }
}
