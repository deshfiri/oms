<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All Orders → Store box is a free-text search: whatever the user types is
 * matched against the store's DFID, business name and name (no need to pick
 * from a dropdown). Every word typed must match.
 */
class AllOrdersStoreSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);

        $sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
        $plain   = $this->store(null, null, 'Corner Shop');

        $this->order($sneaker, 'SNK-1');
        $this->order($sneaker, 'SNK-2');
        $this->order($trend, 'TRD-1');
        $this->order($plain, 'CRN-1');
    }

    private function store(?string $dfid, ?string $business, string $name): Store
    {
        return Store::create([
            'dfid'           => $dfid,
            'business_name'  => $business,
            'name'           => $name,
            'base_url'       => 'http://'.strtolower(str_replace(' ', '', $name)).'.test',
            'api_key'        => 'key-'.$name,
            'api_secret'     => 'secret',
            'webhook_secret' => 'whsec',
            'is_active'      => true,
        ]);
    }

    private function order(Store $store, string $number): void
    {
        OrderMirror::create([
            'store_id'      => $store->id,
            'order_number'  => $number,
            'status'        => 'pending',
            'customer_name' => 'Customer '.$number,
            'placed_at'     => now(),
        ]);
    }

    /** Order numbers shown on /orders for the given store search, sorted. */
    private function ordersFor(?string $store): array
    {
        $res = $this->actingAs($this->admin)->get(route('orders.index', $store === null ? [] : ['store' => $store]));
        $res->assertOk();

        $numbers = $res->viewData('orders')->pluck('order_number')->all();
        sort($numbers);
        return $numbers;
    }

    public function test_no_store_search_shows_every_store(): void
    {
        $this->assertSame(['CRN-1', 'SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor(null));
        $this->assertSame(['CRN-1', 'SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor('   '));
    }

    public function test_search_by_full_store_id(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('DFSNK001'));
        $this->assertSame(['TRD-1'], $this->ordersFor('DFTRD001'));
    }

    public function test_search_by_partial_store_id_is_case_insensitive(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('dfsnk'));
        $this->assertSame(['TRD-1'], $this->ordersFor('trd0'));
    }

    public function test_search_by_business_name(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('Sneaker Lab'));
        $this->assertSame(['TRD-1'], $this->ordersFor('fashion'));
    }

    public function test_search_by_store_name_when_there_is_no_business_name(): void
    {
        $this->assertSame(['CRN-1'], $this->ordersFor('corner'));
    }

    public function test_full_label_from_the_suggestion_list_matches_its_store(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('DFSNK001 · Sneaker Lab BD'));
    }

    public function test_every_word_must_match_the_same_store(): void
    {
        // "bd" alone is in both Sneaker Lab BD and TrendBD …
        $this->assertSame(['SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor('bd'));
        // … but "trend lab" matches no single store.
        $this->assertSame([], $this->ordersFor('trend lab'));
    }

    public function test_unknown_store_returns_no_orders(): void
    {
        $this->assertSame([], $this->ordersFor('zzz'));
        $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'zzz']))
            ->assertOk()
            ->assertSee('0 order(s) match', false);
    }

    public function test_search_box_keeps_what_was_typed(): void
    {
        $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'dfsnk']))
            ->assertOk()
            ->assertSee('name="store"', false)
            ->assertSee('value="dfsnk"', false);
    }

    public function test_store_search_combines_with_other_filters(): void
    {
        OrderMirror::where('order_number', 'SNK-2')->update(['payment_method' => 'bkash']);

        $res = $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'sneaker', 'payment_method' => 'bkash']));
        $this->assertSame(['SNK-2'], $res->viewData('orders')->pluck('order_number')->all());
    }

    public function test_old_store_id_links_still_work(): void
    {
        $trend = Store::where('dfid', 'DFTRD001')->first();

        $res = $this->actingAs($this->admin)->get(route('orders.index', ['store_id' => $trend->id]));
        $this->assertSame(['TRD-1'], $res->viewData('orders')->pluck('order_number')->all());
    }

    public function test_live_refresh_rows_respect_the_store_search(): void
    {
        $this->actingAs($this->admin)->get(route('orders.rows', ['store' => 'trend']))
            ->assertOk()
            ->assertSee('TRD-1')
            ->assertDontSee('SNK-1')
            ->assertDontSee('CRN-1');
    }

    public function test_csv_export_respects_the_store_search(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('orders.export', ['store' => 'DFSNK']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('SNK-1', $csv);
        $this->assertStringContainsString('SNK-2', $csv);
        $this->assertStringNotContainsString('TRD-1', $csv);
        $this->assertStringNotContainsString('CRN-1', $csv);
    }

    public function test_guests_cannot_search_orders(): void
    {
        $this->get(route('orders.index', ['store' => 'dfsnk']))->assertRedirect(route('login'));
    }
}
