<?php

namespace Tests\Unit;

use App\Models\OrderItemMirror;
use App\Models\OrderMirror;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The Blade components shared by every order list, rendered on their own with
 * in-memory orders (no database):
 *  - <x-order-items>    products × quantity cell
 *  - <x-order-pick>     row checkbox carrying the order's counts / products
 *  - <x-order-pick-all> header "select all" checkbox
 */
class OrderComponentsTest extends TestCase
{
    /** An unsaved order with the given [name, qty, variant?] lines. */
    private function order(array $lines, int $id = 7): OrderMirror
    {
        $order = (new OrderMirror)->forceFill(['id' => $id, 'order_number' => "ORD-$id"]);
        $order->exists = true;
        $order->setRelation('items', collect($lines)->map(fn ($l) => (new OrderItemMirror)->forceFill([
            'name' => $l[0], 'qty' => $l[1], 'variant_label' => $l[2] ?? null,
        ])));
        return $order;
    }

    private function render(string $tag, OrderMirror $order): string
    {
        return Blade::render($tag, ['order' => $order]);
    }

    // ── <x-order-items> ──────────────────────────────────────────────────

    public function test_items_lists_every_product_with_its_quantity(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Air Runner', 2, 'Size 42'], ['Sports Socks', 3]]));

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $html);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $html);
        $this->assertStringContainsString('Sports Socks', $html);
    }

    public function test_items_summary_uses_singular_for_one(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Cap', 1]]));

        $this->assertStringContainsString('1 item · 1 pc', $html);
    }

    public function test_items_full_name_is_in_the_hover_title(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Tracksuit Set', 2, 'Navy M']]));

        $this->assertStringContainsString('title="Tracksuit Set · Navy M × 2"', $html);
    }

    public function test_items_shows_three_lines_then_a_more_link_to_the_order(): void
    {
        $order = $this->order(array_map(fn ($i) => ["Product $i", 1], range(1, 5)));

        $html = $this->render('<x-order-items :order="$order"/>', $order);

        $this->assertSame(3, substr_count($html, 'class="oi-qty"'));
        $this->assertStringNotContainsString('Product 4', $html);
        $this->assertStringContainsString('+2 more', $html);
        $this->assertStringContainsString('href="'.route('orders.show', $order).'"', $html);
    }

    public function test_items_more_link_and_limit_can_be_changed(): void
    {
        $order = $this->order(array_map(fn ($i) => ["Product $i", 1], range(1, 5)));

        $html = Blade::render('<x-order-items :order="$order" :limit="4" href="/custom"/>', ['order' => $order]);

        $this->assertSame(4, substr_count($html, 'class="oi-qty"'));
        $this->assertStringContainsString('+1 more', $html);
        $this->assertStringContainsString('href="/custom"', $html);
    }

    public function test_items_shows_a_dash_for_an_order_without_items(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([]));

        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('oi-summary', $html);
        $this->assertStringNotContainsString('oi-qty', $html);
    }

    // ── <x-order-pick> ───────────────────────────────────────────────────

    public function test_pick_carries_the_order_id_and_counts(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([['Air Runner', 2], ['Sports Socks', 3]], 42));

        $this->assertStringContainsString('type="checkbox" value="42" data-order-pick', $html);
        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $html);
        $this->assertStringContainsString('aria-label="Select order ORD-42"', $html);
    }

    public function test_pick_lists_the_products_for_the_details_panel(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([['Air Runner', 2, 'Size 42'], ['Socks "Pro"', 3]]));

        $this->assertSame(1, preg_match('/data-items="([^"]*)"/', $html, $m));
        $this->assertSame([['Air Runner', 'Size 42', 2], ['Socks "Pro"', null, 3]], json_decode(html_entity_decode($m[1]), true));
    }

    public function test_pick_for_an_order_without_items_counts_zero(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([]));

        $this->assertStringContainsString('data-lines="0" data-pcs="0"', $html);
        $this->assertStringContainsString('data-items="[]"', $html);
    }

    public function test_pick_keeps_bulk_form_attributes(): void
    {
        $html = $this->render('<x-order-pick :order="$order" name="order_ids[]" class="row-sel" data-courier="steadfast"/>', $this->order([['Cap', 1]]));

        $this->assertStringContainsString('name="order_ids[]"', $html);
        $this->assertStringContainsString('class="row-sel"', $html);
        $this->assertStringContainsString('data-courier="steadfast"', $html);
        $this->assertStringContainsString('style="accent-color:var(--a-accent)"', $html);
    }

    // ── <x-order-pick-all> ───────────────────────────────────────────────

    public function test_pick_all_is_a_marked_header_checkbox(): void
    {
        $html = Blade::render('<x-order-pick-all id="sel-all"/>');

        $this->assertStringContainsString('type="checkbox" data-order-pick-all', $html);
        $this->assertStringContainsString('id="sel-all"', $html);
        $this->assertStringNotContainsString('value=', $html);   // never submitted / restored as an order id
    }
}
