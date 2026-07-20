<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Mirror\MirrorUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order item price is a snapshot taken at creation. Once an order exists,
 * neither product sync nor an order re-pull may ever rewrite it — but the
 * order-level discount / shipping charge do still sync in (both directions).
 */
class OrderPriceLockTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::create([
            'name'           => 'Test Store',
            'base_url'       => 'http://storefront.test',
            'api_key'        => 'key',
            'api_secret'     => 'secret',
            'webhook_secret' => 'whsec',
            'is_active'      => true,
        ]);
    }

    /** @return array a storefront order payload for order_number ORD-1 */
    private function payload(float $unitPrice, float $discount, float $shipping, string $updatedAt): array
    {
        return [
            'order_number' => 'ORD-1',
            'status'       => 'pending',
            'updated_at'   => $updatedAt,
            'customer'     => ['name' => 'Jane', 'phone' => '01712345678'],
            'totals'       => ['discount' => $discount, 'shipping' => $shipping],
            'items'        => [[
                'sku'        => 'SKU-1',
                'name'       => 'Widget',
                'qty'        => 2,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * 2,
            ]],
        ];
    }

    public function test_item_price_is_locked_after_creation(): void
    {
        $store = $this->store();
        $up    = app(MirrorUpserter::class);

        // Placed with the price that was live at the time.
        $order = $up->order($store, $this->payload(100, 0, 0, '2026-07-20T10:00:00+00:00'));
        $this->assertSame(1, $order->items()->count());
        $this->assertEquals(100.0, (float) $order->items()->first()->unit_price);

        // Product price later changes on the store and the order re-syncs with
        // the new price (and a newer updated_at). The snapshot must NOT move.
        $up->order($store, $this->payload(999, 0, 0, '2026-07-20T12:00:00+00:00'));

        $order->refresh();
        $this->assertSame(1, $order->items()->count(), 'items must not be duplicated on re-sync');
        $this->assertEquals(100.0, (float) $order->items()->first()->unit_price, 'unit price must stay locked');
        $this->assertEquals(200.0, (float) $order->subtotal, 'subtotal is derived from the locked items');
    }

    public function test_discount_and_shipping_sync_in_but_prices_do_not(): void
    {
        $store = $this->store();
        $up    = app(MirrorUpserter::class);

        $order = $up->order($store, $this->payload(100, 0, 0, '2026-07-20T10:00:00+00:00'));

        // Store-side discount + shipping change (with a newer timestamp) flows in.
        $up->order($store, $this->payload(999, 30, 60, '2026-07-20T12:00:00+00:00'));

        $order->refresh();
        $this->assertEquals(100.0, (float) $order->items()->first()->unit_price, 'price still locked');
        $this->assertEquals(30.0,  (float) $order->discount);
        $this->assertEquals(60.0,  (float) $order->shipping_total);
        // grand = subtotal(200 locked) - discount(30) + shipping(60)
        $this->assertEquals(230.0, (float) $order->grand_total);
    }

    public function test_stale_resync_cannot_revert_a_fresh_oms_edit(): void
    {
        $store = $this->store();
        $up    = app(MirrorUpserter::class);

        $up->order($store, $this->payload(100, 0, 0, '2026-07-20T10:00:00+00:00'));

        // Simulate a CS agent editing the discount in the OMS: value set + our copy
        // marked as the freshest (updated_at_remote bumped to "now" = far future).
        $order = OrderMirror::where('order_number', 'ORD-1')->first();
        $order->forceFill([
            'discount'          => 50,
            'grand_total'       => 150,
            'updated_at_remote' => '2026-07-20T18:00:00+00:00',
        ])->save();

        // An in-flight STALE re-pull (older updated_at) carrying the pre-edit
        // discount=0 must NOT revert the agent's edit.
        $up->order($store, $this->payload(100, 0, 0, '2026-07-20T11:00:00+00:00'));

        $order->refresh();
        $this->assertEquals(50.0,  (float) $order->discount, 'stale re-pull must not revert the OMS edit');
        $this->assertEquals(150.0, (float) $order->grand_total);
    }
}
