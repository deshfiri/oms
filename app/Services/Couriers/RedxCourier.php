<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

class RedxCourier implements CourierAdapter
{
    public function slug(): string        { return 'redx'; }
    public function displayName(): string { return 'RedX'; }

    public function capabilities(): array
    {
        // RedX returns a single tracking id that doubles as the consignment id.
        // Booked through the store website's courier API.
        return ['provides_consignment'=>true, 'provides_tracking'=>true, 'direct_api'=>false, 'manual_entry'=>false];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        throw new CourierUnavailableException('RedX is booked through the store website — no direct OMS booking.');
    }

    public function normalizeStatus(string $native): string
    {
        return match (strtolower($native)) {
            'created','pickup_pending'   => 'booked',
            'pickup_success','picked_up' => 'picked_up',
            'in_transit','hub_in'        => 'in_transit',
            'arrived_at_hub'             => 'hub_received',
            'on_route'                   => 'out_for_delivery',
            'delivered'                  => 'delivered',
            'failed'                     => 'delivery_failed',
            'returned'                   => 'returned',
            default                      => $native,
        };
    }
}
