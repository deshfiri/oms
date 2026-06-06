<?php

namespace App\Services\Storefront\Resources;

class ShippingResource extends BaseResource
{
    /**
     * Delivery-fee quote from the storefront — the SAME zone-based calculator the
     * website checkout uses, so the OMS never invents a charge.
     */
    public function quote(string $zone, float $subtotal = 0, ?string $city = null): array
    {
        return $this->c->request('POST', 'shipping/quote', array_filter([
            'zone'     => $zone,
            'subtotal' => $subtotal,
            'city'     => $city,
        ], fn ($v) => $v !== null));
    }

    /** District/area reference list from the storefront. */
    public function areas(array $filters = []): array
    {
        return $this->c->request('GET', 'shipping/areas', [], $filters);
    }
}
