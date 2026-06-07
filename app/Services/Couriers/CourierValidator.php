<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;
use Illuminate\Support\Facades\Log;

/**
 * Pre-booking sanity check.
 *
 * The storefront is the source of truth for courier selection — CourierPicker
 * already validated that a known slug was returned from storefront settings.
 * This class only verifies that the OMS adapter for that slug is registered,
 * so a mis-spelled slug from the storefront is caught before any API call.
 */
class CourierValidator
{
    public function validate(OrderMirror $order, string $slug): void
    {
        if (! array_key_exists($slug, CourierManager::ADAPTERS)) {
            throw new CourierConfigException(
                "Courier \"{$slug}\" (returned by storefront settings) is not a recognised OMS adapter. "
                    . 'Valid values: ' . implode(', ', array_keys(CourierManager::ADAPTERS)) . '. '
                    . 'Update the storefront courier settings to use one of these values.'
            );
        }

        Log::info('[CourierValidator] passed', [
            'order'    => $order->order_number,
            'store_id' => $order->store?->id,
            'courier'  => $slug,
        ]);
    }
}
