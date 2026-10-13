<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order lists have a checkbox per order; ticking orders shows how many orders,
 * products and pieces are selected. The checkbox carries the order's counts.
 */
class OrderPickTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);

        $this->store = Store::create([
            'dfid' => 'DFSNK001', 'business_name' => 'Sneaker Lab BD', 'name' => 'sneakerlab',
            'base_url' => 'http://sneakerlab.test', 'api_key' => 'key', 'api_secret' => 'secret',
            'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function order(string $number, array $items, string $status = 'pending'): OrderMirror
    {
        $o = OrderMirror::create([
            'store_id' => $this->store->id, 'order_number' => $number,
            'status' => $status, 'placed_at' => now(),
        ]);
        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        return $o;
    }

    /** The order's row checkbox tag. */
    private function pickFor(string $html, OrderMirror $o): string
    {
        $this->assertSame(1, preg_match('/<input type="checkbox" value="'.$o->id.'" data-order-pick[^>]*>/', $html, $m),
            "checkbox for {$o->order_number} not found");
        return $m[0];
    }

    public function test_all_orders_rows_carry_product_and_piece_counts(): void
    {
        $a = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2], ['name' => 'Sports Socks', 'qty' => 3]]);
        $b = $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 1]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $this->pickFor($html, $a));
        $this->assertStringContainsString('data-lines="1" data-pcs="1"', $this->pickFor($html, $b));
        $this->assertStringContainsString('data-order-pick-all', $html);
        $this->assertStringContainsString('id="order-pick-bar"', $html);
    }

    public function test_checkbox_lists_the_products_for_the_details_panel(): void
    {
        $o = $this->order('ORD-1', [
            ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
            ['name' => 'Sports Socks', 'qty' => 3],
        ]);

        $pick = $this->pickFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), $o);

        $this->assertSame(1, preg_match('/data-items="([^"]*)"/', $pick, $m));
        $this->assertSame([['Air Runner', 'Size 42', 2], ['Sports Socks', null, 3]],
            json_decode(html_entity_decode($m[1]), true));
    }

    public function test_order_without_items_counts_zero(): void
    {
        $o = $this->order('ORD-1', []);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('data-lines="0" data-pcs="0"', $this->pickFor($html, $o));
    }

    public function test_live_refresh_rows_keep_the_checkboxes(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 4]]);

        $html = $this->actingAs($this->admin)->get(route('orders.rows'))->assertOk()->getContent();

        $this->assertStringContainsString('data-lines="1" data-pcs="4"', $this->pickFor($html, $o));
    }

    public function test_other_order_lists_have_the_checkbox(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);

        foreach (['inbox.index', 'dashboard'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('data-pcs="2"', $this->pickFor($html, $o), $route);
        }
    }

    public function test_bulk_action_checkboxes_still_submit_order_ids(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 3]], S::PENDING_VERIFICATION);

        $pick = $this->pickFor($this->actingAs($this->admin)->get(route('verification.index'))->assertOk()->getContent(), $o);

        $this->assertStringContainsString('name="order_ids[]"', $pick);
        $this->assertStringContainsString('class="row-sel"', $pick);
        $this->assertStringContainsString('data-pcs="3"', $pick);
    }
}
