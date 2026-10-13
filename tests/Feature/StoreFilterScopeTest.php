<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The query pieces behind every order list's store search:
 * Store::matching(), OrderMirror::storeFilter() and Store::forPicker().
 */
class StoreFilterScopeTest extends TestCase
{
    use RefreshDatabase;

    private Store $sneaker;
    private Store $trend;
    private Store $corner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $this->trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
        $this->corner  = $this->store(null, null, 'Corner Shop');

        foreach ([$this->sneaker, $this->trend, $this->corner] as $s) {
            OrderMirror::create(['store_id' => $s->id, 'order_number' => 'ORD-'.$s->id, 'status' => 'pending', 'placed_at' => now()]);
        }
    }

    private function store(?string $dfid, ?string $business, string $name): Store
    {
        return Store::create([
            'dfid' => $dfid, 'business_name' => $business, 'name' => $name,
            'base_url' => 'http://'.str_replace(' ', '', strtolower($name)).'.test', 'api_key' => "key-$name",
            'api_secret' => 'secret', 'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function matching(?string $term): array
    {
        return Store::matching($term)->orderBy('id')->pluck('id')->all();
    }

    private function filtered($storeId, ?string $term): array
    {
        return OrderMirror::storeFilter($storeId, $term)->orderBy('id')->pluck('store_id')->all();
    }

    public function test_matching_finds_by_dfid_business_name_or_name(): void
    {
        $this->assertSame([$this->sneaker->id], $this->matching('DFSNK001'));
        $this->assertSame([$this->sneaker->id], $this->matching('snk'));
        $this->assertSame([$this->trend->id], $this->matching('Fashion'));
        $this->assertSame([$this->corner->id], $this->matching('corner'));   // no DFID / business name
    }

    public function test_matching_needs_every_word_in_the_same_store(): void
    {
        $this->assertSame([$this->sneaker->id, $this->trend->id], $this->matching('bd'));
        $this->assertSame([$this->sneaker->id], $this->matching('bd lab'));
        $this->assertSame([], $this->matching('trend lab'));
    }

    public function test_matching_accepts_the_full_suggestion_label(): void
    {
        $this->assertSame([$this->trend->id], $this->matching('DFTRD001 · TrendBD Fashion'));
    }

    public function test_blank_search_matches_every_store(): void
    {
        $all = [$this->sneaker->id, $this->trend->id, $this->corner->id];
        $this->assertSame($all, $this->matching(null));
        $this->assertSame($all, $this->matching('   '));
    }

    public function test_store_filter_by_text(): void
    {
        $this->assertSame([$this->sneaker->id], $this->filtered(null, 'sneaker'));
        $this->assertSame([], $this->filtered(null, 'zzz'));
    }

    public function test_store_filter_by_old_store_id(): void
    {
        $this->assertSame([$this->trend->id], $this->filtered($this->trend->id, null));
    }

    public function test_store_filter_with_id_and_text_needs_both(): void
    {
        $this->assertSame([$this->trend->id], $this->filtered($this->trend->id, 'fashion'));
        $this->assertSame([], $this->filtered($this->trend->id, 'sneaker'));
    }

    public function test_store_filter_without_input_keeps_every_order(): void
    {
        $this->assertSame([$this->sneaker->id, $this->trend->id, $this->corner->id], $this->filtered(null, null));
        $this->assertSame([$this->sneaker->id, $this->trend->id, $this->corner->id], $this->filtered('', ''));
    }

    public function test_picker_lists_stores_by_business_name_with_only_the_needed_columns(): void
    {
        $stores = Store::forPicker();

        $this->assertSame(['id', 'dfid', 'business_name', 'name'], array_keys($stores->first()->getAttributes()));
        $this->assertSame(
            Store::orderBy('business_name')->pluck('id')->all(),
            $stores->pluck('id')->all(),
        );
    }
}
