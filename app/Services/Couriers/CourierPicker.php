<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolve which courier to use for an order.
 *
 * The storefront is the single source of truth.
 * OMS reads  settings.group='couriers'  from the connected storefront and
 * uses the value of  settings.key='default_courier'  (and optionally
 * 'outside_dhaka_courier' for zone-based override).
 *
 * OMS never maintains its own courier selection — no hardcoded values, no
 * OMS-side default_courier column, no automatic substitution.
 * If the storefront has no valid courier configured, dispatch fails with a
 * clear error so the operator can fix the storefront settings.
 */
class CourierPicker
{
    /** How long to cache storefront courier settings per store (seconds). */
    private const CACHE_TTL = 300;

    public function pickFor(OrderMirror $order): string
    {
        $store = $order->store;

        Log::info('[CourierPicker] resolving courier from storefront settings', [
            'order'        => $order->order_number,
            'store_id'     => $store?->id,
            'shipping_zone'=> $order->shipping_zone,
        ]);

        if (! $store) {
            throw new CourierConfigException(
                "Order {$order->order_number} has no associated store — cannot determine which courier to use."
            );
        }

        $settings = $this->fetchCourierSettings($store);

        Log::info('[CourierPicker] storefront courier settings received', [
            'order'    => $order->order_number,
            'store_id' => $store->id,
            'settings' => $settings,
        ]);

        // Zone-based override: outside_dhaka_courier when zone is not inside_dhaka.
        if (! empty($settings['outside_dhaka_courier'])
            && $order->shipping_zone
            && $order->shipping_zone !== 'inside_dhaka'
        ) {
            $outside = $this->validSlug($settings['outside_dhaka_courier']);
            if ($outside) {
                Log::info('[CourierPicker] selected outside_dhaka_courier', ['order' => $order->order_number, 'slug' => $outside]);
                return $outside;
            }
        }

        // Primary: default_courier from storefront settings.
        $slug = $this->validSlug($settings['default_courier'] ?? null);

        if (! $slug) {
            throw new CourierConfigException(
                "Store \"{$store->name}\" has no valid default_courier in its storefront courier settings "
                    . "(settings.group='couriers', settings.key='default_courier'). "
                    . "Configure it in the storefront admin panel."
            );
        }

        Log::info('[CourierPicker] selected default_courier', ['order' => $order->order_number, 'store_id' => $store->id, 'slug' => $slug]);
        return $slug;
    }

    /**
     * Fetch and cache the couriers settings group from the storefront.
     * Throws CourierConfigException if the storefront cannot be reached,
     * so dispatch fails loudly rather than silently using a wrong courier.
     */
    private function fetchCourierSettings($store): array
    {
        $cacheKey = "oms:store:{$store->id}:courier_settings";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $settings = (new StorefrontClient($store))->settings()->group('couriers');
            Cache::put($cacheKey, $settings, self::CACHE_TTL);
            return $settings;
        } catch (\Throwable $e) {
            Log::warning('[CourierPicker] failed to fetch storefront courier settings', [
                'store_id' => $store->id,
                'err'      => $e->getMessage(),
            ]);
            throw new CourierConfigException(
                "Could not read courier settings from storefront for store \"{$store->name}\": {$e->getMessage()}. "
                    . "Check the storefront connection (Stores → Ping) and that GET /api/v1/settings/couriers is implemented."
            );
        }
    }

    /** Return $raw if it is a known adapter slug, otherwise null. */
    private function validSlug(?string $raw): ?string
    {
        if (! $raw) return null;
        $slug = strtolower(trim($raw));
        return array_key_exists($slug, CourierManager::ADAPTERS) ? $slug : null;
    }
}
