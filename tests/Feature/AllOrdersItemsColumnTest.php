<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All Orders shows each order's products with their quantity right in the
 * table, so staff don't have to open every order to see what's in it.
 */
class AllOrdersItemsColumnTest extends TestCase
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

    private function order(string $number, array $items): OrderMirror
    {
        $o = OrderMirror::create([
            'store_id' => $this->store->id, 'order_number' => $number,
            'status' => 'pending', 'placed_at' => now(),
        ]);
        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        return $o;
    }

    private function cellFor(string $html, string $orderNumber): string
    {
        // The row of the given order, from its order-number link to the row end.
        $start = strpos($html, '>'.$orderNumber.'</a>');
        $this->assertNotFalse($start, "row for $orderNumber not found");
        return substr($html, $start, strpos($html, '</tr>', $start) - $start);
    }

    public function test_table_has_items_column(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 1]]);

        $this->actingAs($this->admin)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('<th>Items</th>', false);
    }

    public function test_every_product_is_shown_with_its_quantity(): void
    {
        $this->order('ORD-1', [
            ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
            ['name' => 'Sports Socks', 'qty' => 3],
        ]);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $row);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $row);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $row);
        $this->assertStringContainsString('Sports Socks', $row);
    }

    public function test_summary_shows_line_and_piece_counts(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2], ['name' => 'Sports Socks', 'qty' => 3]]);
        $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 1]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('2 items · 5 pcs', $this->cellFor($html, 'ORD-1'));
        $this->assertStringContainsString('1 item · 1 pc', $this->cellFor($html, 'ORD-2'));
    }

    public function test_long_orders_show_three_items_then_a_more_link(): void
    {
        $order = $this->order('ORD-1', array_map(fn ($i) => ['name' => "Product $i", 'qty' => 1], range(1, 5)));

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('5 items · 5 pcs', $row);
        $this->assertSame(3, substr_count($row, 'class="oi-qty"'));
        $this->assertStringContainsString('+2 more', $row);
        $this->assertStringContainsString(route('orders.show', $order), $row);
    }

    public function test_full_name_is_available_on_hover(): void
    {
        $this->order('ORD-1', [['name' => 'Tracksuit Set — Navy M', 'variant_label' => 'Slim', 'qty' => 2]]);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('title="Tracksuit Set — Navy M · Slim × 2"', $row);
    }

    public function test_items_stay_with_their_own_order(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);
        $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 7]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('Air Runner', $this->cellFor($html, 'ORD-1'));
        $this->assertStringNotContainsString('Canvas Slip-on', $this->cellFor($html, 'ORD-1'));
        $this->assertStringContainsString('Canvas Slip-on', $this->cellFor($html, 'ORD-2'));
        $this->assertStringContainsString('<span class="oi-qty">7×</span>', $this->cellFor($html, 'ORD-2'));
    }

    public function test_order_without_items_shows_a_dash(): void
    {
        $this->order('ORD-1', []);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringNotContainsString('oi-qty', $row);
        $this->assertStringNotContainsString('oi-summary', $row);
        $this->assertStringContainsString('—', $row);
    }

    public function test_live_refresh_rows_also_show_items(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);

        $this->actingAs($this->admin)->get(route('orders.rows'))
            ->assertOk()
            ->assertSee('<th>Items</th>', false)
            ->assertSee('Air Runner')
            ->assertSee('<span class="oi-qty">2×</span>', false);
    }

    public function test_items_are_loaded_without_a_query_per_order(): void
    {
        foreach (range(1, 10) as $i) {
            $this->order("ORD-$i", [['name' => "Item $i", 'qty' => $i]]);
        }

        \DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('orders.index'))->assertOk();
        $itemQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'order_items_mirror'))
            ->count();

        $this->assertSame(1, $itemQueries, 'items must be eager-loaded in one query, not one per order');
    }
}
