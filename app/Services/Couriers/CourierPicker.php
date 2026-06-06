<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;

/**
 * Decide which courier to use for an order.
 *
 * Priority:
 *   1. The store's outside-Dhaka courier for non-Dhaka zones.
 *   2. The store's default courier (configured in the Client Panel).
 *   3. The courier chosen on the store website for this specific order
 *      (orders_mirror.preferred_courier) — fallback only when no store
 *      courier is configured.
 *   4. 'steadfast' as a last resort.
 */
class CourierPicker
{
    public function pickFor(OrderMirror $order): string
    {
        $store = $order->store;

        if (! $store) {
            return $this->normalize($order->preferred_courier) ?? 'steadfast';
        }

        // 1. Zone-based store rule.
        $outside = $store->outside_dhaka_courier;
        if ($outside && $order->shipping_zone && $order->shipping_zone !== 'inside_dhaka') {
            return $outside;
        }

        // 2. Store's default courier — the Client Panel setting always wins.
        if ($store->default_courier) {
            return $store->default_courier;
        }

        // 3. Fall back to the courier assigned by the store website.
        $preferred = $this->normalize($order->preferred_courier);
        if ($preferred) return $preferred;

        // 4.
        return 'steadfast';
    }

    /** Reduce a free-text courier label to a known adapter slug, or null. */
    private function normalize(?string $raw): ?string
    {
        if (! $raw) return null;
        $k = strtolower(trim($raw));
        $k = str_replace([' ', '-', '_'], '', $k);
        return match (true) {
            str_contains($k, 'steadfast') || $k === 'sf'             => 'steadfast',
            str_contains($k, 'pathao')                               => 'pathao',
            str_contains($k, 'redx')                                 => 'redx',
            str_contains($k, 'carrybee') || str_contains($k, 'carry') => 'carrybee',
            str_contains($k, 'manual') || str_contains($k, 'inhouse') || str_contains($k, 'own') => 'manual',
            default => array_key_exists($k, CourierManager::ADAPTERS) ? $k : null,
        };
    }
}
