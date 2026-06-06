<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

class ManualCourier implements CourierAdapter
{
    public function slug(): string        { return 'manual'; }
    public function displayName(): string { return 'Manual / In-house'; }

    public function capabilities(): array
    {
        // In-house delivery: the operator writes the consignment number from the
        // physical slip by hand. No tracking, no API. Nothing is auto-generated.
        return ['provides_consignment'=>true, 'provides_tracking'=>false, 'direct_api'=>false, 'manual_entry'=>true];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        // Nothing to call and nothing to fake — the consignment id is entered
        // manually after the parcel is handed over. Returns empty ids so the
        // booker creates a pending consignment awaiting manual entry.
        return [
            'consignment_id' => null,
            'tracking_code'  => null,
            'tracking_url'   => null,
            'label_url'      => null,
            'raw'            => ['manual_entry' => true, 'order' => $order->order_number],
        ];
    }

    public function normalizeStatus(string $native): string { return $native; }
}
