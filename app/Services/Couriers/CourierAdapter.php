<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

interface CourierAdapter
{
    public function slug(): string;
    public function displayName(): string;

    /**
     * What this courier actually provides, so the UI never shows fake/empty
     * data. Keys:
     *   'provides_consignment' => bool  (gives a consignment id)
     *   'provides_tracking'    => bool  (gives a separate tracking code/page)
     *   'direct_api'           => bool  (OMS can book it directly; false ⇒ the
     *                                    store website's courier API books it)
     *   'manual_entry'         => bool  (operator types the id from the slip)
     *
     * @return array{provides_consignment:bool,provides_tracking:bool,direct_api:bool,manual_entry:bool}
     */
    public function capabilities(): array;

    /**
     * Book a consignment. Must return an array with these keys:
     *
     *  [
     *    'consignment_id' => string|null,
     *    'tracking_code'  => string|null,
     *    'tracking_url'   => string|null,
     *    'label_url'      => string|null,
     *    'raw'            => array (full raw API response),
     *  ]
     */
    public function bookConsignment(OrderMirror $order, array $opts = []): array;

    /**
     * Map a courier-native status string to our internal taxonomy
     * (picked_up, in_transit, hub_received, out_for_delivery, delivered,
     *  delivery_failed, return_initiated, returned, lost).
     */
    public function normalizeStatus(string $native): string;
}
