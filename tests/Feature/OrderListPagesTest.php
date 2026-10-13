<?php

namespace Tests\Feature;

use App\Models\CourierConsignment;
use App\Models\CustomerMirror;
use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every order list in the OMS has the same three things:
 *  - a free-text store search (DFID / store name),
 *  - each order's products × quantity,
 *  - a checkbox per order carrying its product / piece counts for the selection bar.
 * Each page is checked here with an order from two different stores.
 */
class OrderListPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $sneaker;
    private Store $trend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);
        $this->sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $this->trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
    }

    /**
     * page => [index route, AJAX rows route|null, how to make an order show up there].
     * Pages without a store search (dashboard, customer page) are covered separately.
     */
    public static function pages(): array
    {
        return [
            'all orders'   => ['orders.index',       'orders.rows',       ['status' => 'pending']],
            'inbox'        => ['inbox.index',        null,                ['status' => 'pending']],
            'my orders'    => ['orders-new.index',   null,                ['status' => S::PENDING_VERIFICATION, 'placed_by_admin' => true]],
            'verification' => ['verification.index', 'verification.rows', ['status' => S::PENDING_VERIFICATION]],
            'processing'   => ['processing.index',   'processing.rows',   ['status' => S::PROCESSING]],
            'packing'      => ['packing.index',      'packing.rows',      ['status' => S::PROCESSING, 'consignment' => true]],
            'dispatch'     => ['dispatch.index',     'dispatch.rows',     ['status' => S::PACKED, 'consignment' => true]],
            'returns'      => ['returns.index',      'returns.rows',      ['status' => S::RETURN_PENDING, 'return_pending_at' => true]],
            'refunds'      => ['refunds.index',      null,                ['status' => S::RETURNED, 'refund_status' => 'pending']],
        ];
    }

    /** The pages whose table is refreshed in place over AJAX. */
    public static function pagesWithRows(): array
    {
        return array_filter(self::pages(), fn ($p) => $p[1] !== null);
    }

    private function store(string $dfid, string $business, string $name): Store
    {
        return Store::create([
            'dfid' => $dfid, 'business_name' => $business, 'name' => $name,
            'base_url' => "http://$name.test", 'api_key' => "key-$name", 'api_secret' => 'secret',
            'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function order(Store $store, string $number, array $page, array $items): OrderMirror
    {
        $o = OrderMirror::create(array_filter([
            'store_id'          => $store->id,
            'order_number'      => $number,
            'status'            => $page['status'],
            'customer_name'     => "Customer $number",
            'customer_phone'    => '01700000000',
            'customer_email'    => 'buyer@test.local',
            'placed_at'         => now(),
            'placed_by_user_id' => ($page['placed_by_admin'] ?? false) ? $this->admin->id : null,
            'return_pending_at' => ($page['return_pending_at'] ?? false) ? now() : null,
            'refund_status'     => $page['refund_status'] ?? null,
        ], fn ($v) => $v !== null));

        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        if ($page['consignment'] ?? false) {
            CourierConsignment::create([
                'order_id' => $o->id, 'courier_slug' => 'steadfast', 'courier_name' => 'Steadfast',
                'consignment_id' => "CID-$number", 'tracking_code' => "TRK-$number", 'booked_at' => now(),
            ]);
        }
        return $o;
    }

    /** Two orders, one per store: SNK-1 (2× Air Runner + 3× Sports Socks) and TRD-1 (1× Denim Jacket). */
    private function seedOrders(array $page): array
    {
        return [
            $this->order($this->sneaker, 'SNK-1', $page, [
                ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
                ['name' => 'Sports Socks', 'qty' => 3],
            ]),
            $this->order($this->trend, 'TRD-1', $page, [['name' => 'Denim Jacket', 'qty' => 1]]),
        ];
    }

    /** Page HTML; JSON row responses ({tbody, pagination} / {html}) are flattened to their HTML. */
    private function html(string $route, array $query = []): string
    {
        $res = $this->actingAs($this->admin)->get(route($route, $query));
        $res->assertOk();
        $json = json_decode($res->getContent(), true);
        return is_array($json) ? implode("\n", array_filter($json, 'is_string')) : $res->getContent();
    }

    private function pick(string $html, OrderMirror $o): string
    {
        $this->assertSame(1, preg_match('/<input type="checkbox" value="'.$o->id.'" data-order-pick[^>]*>/', $html, $m),
            "no checkbox for {$o->order_number}");
        return $m[0];
    }

    // ── items × quantity ─────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_page_shows_each_orders_products_with_quantity(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route);

        $this->assertStringContainsString('<th>Items</th>', $html);
        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $html);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $html);
        $this->assertStringContainsString('1 item · 1 pc', $html);
        $this->assertStringContainsString('Denim Jacket', $html);
    }

    #[DataProvider('pages')]
    public function test_page_loads_items_in_one_query(string $route, ?string $rows, array $page): void
    {
        foreach (range(1, 6) as $i) {
            $this->order($i % 2 ? $this->sneaker : $this->trend, "ORD-$i", $page, [['name' => "Item $i", 'qty' => $i]]);
        }

        DB::enableQueryLog();
        $this->html($route);
        $itemQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'order_items_mirror'))->count();

        $this->assertLessThanOrEqual(1, $itemQueries, 'items must be eager-loaded, not queried per order');
    }

    // ── selection checkbox ───────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_page_has_a_checkbox_per_order_with_its_counts(string $route, ?string $rows, array $page): void
    {
        [$snk, $trd] = $this->seedOrders($page);

        $html = $this->html($route);

        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringContainsString('data-lines="1" data-pcs="1"', $this->pick($html, $trd));
        $this->assertStringContainsString('data-order-pick-all', $html);
        $this->assertStringContainsString('id="order-pick-bar"', $html);
    }

    // ── store search ─────────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_store_search_by_id_or_name_filters_the_page(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        foreach (['DFSNK001', 'dfsnk', 'Sneaker Lab', 'DFSNK001 · Sneaker Lab BD'] as $term) {
            $html = $this->html($route, ['store' => $term]);
            $this->assertStringContainsString('SNK-1', $html, "'$term' should find SNK-1");
            $this->assertStringNotContainsString('TRD-1', $html, "'$term' should hide TRD-1");
        }

        $html = $this->html($route, ['store' => 'trendbd fashion']);
        $this->assertStringContainsString('TRD-1', $html);
        $this->assertStringNotContainsString('SNK-1', $html);
    }

    #[DataProvider('pages')]
    public function test_unknown_store_shows_no_orders(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route, ['store' => 'zzz']);

        $this->assertStringNotContainsString('SNK-1', $html);
        $this->assertStringNotContainsString('TRD-1', $html);
    }

    #[DataProvider('pages')]
    public function test_store_search_box_is_shown_and_keeps_what_was_typed(string $route, ?string $rows, array $page): void
    {
        $html = $this->html($route, ['store' => 'dfsnk']);

        $this->assertStringContainsString('data-store-search', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="store"[^>]*value="dfsnk"/', $html);
    }

    #[DataProvider('pages')]
    public function test_old_store_id_links_still_filter(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route, ['store_id' => $this->trend->id]);

        $this->assertStringContainsString('TRD-1', $html);
        $this->assertStringNotContainsString('SNK-1', $html);
    }

    // ── live refresh (AJAX rows) ─────────────────────────────────────────

    #[DataProvider('pagesWithRows')]
    public function test_live_refresh_rows_keep_store_search_items_and_checkboxes(string $route, string $rows, array $page): void
    {
        [$snk] = $this->seedOrders($page);

        $html = $this->html($rows, ['store' => 'sneaker']);

        $this->assertStringContainsString('SNK-1', $html);
        $this->assertStringNotContainsString('TRD-1', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
    }

    // ── pages without a store search ─────────────────────────────────────

    public function test_dashboard_recent_orders_show_items_and_checkboxes(): void
    {
        [$snk, $trd] = $this->seedOrders(['status' => 'pending']);

        $html = $this->html('dashboard');

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('Denim Jacket', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringContainsString('data-pcs="1"', $this->pick($html, $trd));
    }

    public function test_customer_order_history_shows_items_and_checkboxes(): void
    {
        [$snk] = $this->seedOrders(['status' => 'pending']);
        $customer = CustomerMirror::create([
            'store_id' => $this->sneaker->id, 'customer_id' => 1,
            'name' => 'Buyer', 'email' => 'buyer@test.local', 'phone' => '01700000000',
        ]);

        $html = $this->html('customers.show', ['customer' => $customer]);

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringNotContainsString('TRD-1', $html);   // other store's order isn't this customer's
    }
}
