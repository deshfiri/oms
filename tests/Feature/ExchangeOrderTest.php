<?php

namespace Tests\Feature;

use App\Models\CourierConsignment;
use App\Models\Exchange;
use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Register order → "Exchange order": creates the replacement for a delivered
 * order. Steadfast's API has no exchange option, so exchange orders are never
 * auto-booked as regular Steadfast parcels; the consignment created in the
 * Steadfast panel is entered in the OMS and saved only once Steadfast confirms it.
 */
class ExchangeOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $store;

    /** Requests the fake courier/storefront received. */
    private array $sent = [];
    /** Response for Steadfast status_by_cid: array body, or 'down' for a network error. */
    private array|string $steadfastStatus = ['status' => 200, 'delivery_status' => 'in_review'];
    private string $courier = 'steadfast';

    protected function setUp(): void
    {
        parent::setUp();

        config(['couriers.steadfast.api_key' => 'sf-key', 'couriers.steadfast.secret' => 'sf-secret']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);
        $this->store = $this->makeStore('DFSHOP01', 'https://shop.test');

        Http::fake(function (HttpRequest $req) {
            $this->sent[] = $req;
            $url = $req->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/status_by_cid/')) {
                if ($this->steadfastStatus === 'down') throw new ConnectionException('Connection timed out');
                return Http::response($this->steadfastStatus, 200);
            }
            if (str_contains($url, 'portal.packzy.com/api/v1/create_order')) {
                return Http::response(['status' => 200, 'consignment' => ['consignment_id' => 999, 'tracking_code' => 'SFR999']]);
            }
            if (str_ends_with($url, '/api/v1/settings/couriers')) {
                return Http::response(['ok' => true, 'data' => ['default_courier' => $this->courier]]);
            }
            if ($req->method() === 'POST' && preg_match('#/api/v1/orders$#', $url)) {
                $d = $req->data();
                return Http::response(['data' => [
                    'order_number'   => ($d['source'] ?? '') === 'oms_exchange' ? 'SHOP-EX-1' : 'SHOP-NEW-1',
                    'status'         => $d['status'] ?? 'pending',
                    'payment_method' => $d['payment_method'] ?? 'cod',
                    'subtotal'       => 1100, 'discount' => $d['discount'] ?? 0,
                    'shipping_total' => $d['shipping_total'] ?? 0,
                    'grand_total'    => 1100 - ($d['discount'] ?? 0) + ($d['shipping_total'] ?? 0),
                    'customer'       => $d['customer'] ?? [],
                    'shipping'       => $d['shipping'] ?? [],
                    'items'          => [['sku' => 'NEW-1', 'name' => 'New Shirt', 'quantity' => 1, 'unit_price' => 1100]],
                ]], 201);
            }
            if ($req->method() === 'POST' && str_contains($url, '/shipments')) {
                return Http::response(['data' => []]); // storefront can't book — OMS adapter paths decide
            }
            return Http::response(['ok' => true, 'data' => []]);
        });
    }

    private function makeStore(string $dfid, string $url): Store
    {
        $s = new Store(['name' => $dfid, 'business_name' => $dfid, 'dfid' => $dfid, 'base_url' => $url,
            'api_key' => 'k', 'webhook_secret' => 'wh', 'is_active' => true]);
        $s->api_secret = 's';
        $s->save();
        return $s;
    }

    private function order(string $number, string $status, array $extra = []): OrderMirror
    {
        $o = OrderMirror::create($extra + [
            'store_id' => $this->store->id, 'order_number' => $number, 'status' => $status,
            'customer_name' => 'Nahida', 'customer_phone' => '01603009863',
            'shipping_name' => 'Nahida', 'shipping_phone' => '01603009863',
            'shipping_address_line' => 'Holan, Dakshinkhan', 'shipping_city' => 'Dhaka', 'shipping_zone' => 'inside_dhaka',
            'payment_method' => 'cod', 'subtotal' => 999, 'grand_total' => 999, 'placed_at' => now(),
        ]);
        $o->items()->create(['sku' => 'OLD-1', 'name' => 'Old Shirt', 'qty' => 1, 'unit_price' => 999, 'line_total' => 999]);
        return $o;
    }

    private function exchangePayload(OrderMirror $original, array $over = []): array
    {
        return $over + [
            'is_exchange' => 1, 'store_id' => $this->store->id, 'original_order_id' => $original->id,
            'exchange_reason' => 'size_issue', 'discount' => 100, 'notes' => 'Size L instead of M',
            'items' => [['sku' => 'NEW-1', 'qty' => 1]],
        ];
    }

    private function sentTo(string $needle, ?string $method = null): array
    {
        return array_values(array_filter($this->sent, fn (HttpRequest $r) =>
            str_contains($r->url(), $needle) && (! $method || $r->method() === $method)));
    }

    // ── Register order: exchange mode ─────────────────────────────────────

    public function test_register_order_page_has_the_exchange_toggle(): void
    {
        $this->actingAs($this->admin)->get(route('orders-new.create'))->assertOk()
            ->assertSee('Exchange order')->assertSee('name="is_exchange"', false)
            ->assertSee('name="original_order_id"', false)->assertSee('name="exchange_reason"', false);
    }

    public function test_exchange_mode_creates_a_linked_replacement_and_original_awaits_the_return(): void
    {
        $original = $this->order('SHOP-100', S::DELIVERED);

        $this->actingAs($this->admin)->post(route('orders-new.store'), $this->exchangePayload($original))
            ->assertRedirect(route('orders-new.index'))->assertSessionHas('status');

        $create = $this->sentTo('/api/v1/orders', 'POST');
        $this->assertCount(1, $create);
        $d = $create[0]->data();
        $this->assertSame('oms_exchange', $d['source']);
        $this->assertTrue($d['is_exchange']);
        $this->assertSame('SHOP-100', $d['exchange_of']);
        $this->assertSame('processing', $d['status']);
        $this->assertEquals(0, $d['shipping_total']);
        $this->assertEquals(100, $d['discount']);
        $this->assertSame('01603009863', $d['shipping']['phone']); // ships to the original customer
        $this->assertSame([['sku' => 'NEW-1', 'quantity' => 1]], $d['items']);

        $rep = OrderMirror::where('order_number', 'SHOP-EX-1')->firstOrFail();
        $this->assertSame($original->id, $rep->exchange_of_order_id);
        $this->assertSame(S::PROCESSING, $rep->status);
        $this->assertSame($this->admin->id, $rep->placed_by_user_id);

        $ex = Exchange::firstOrFail();
        $this->assertSame([$original->id, $rep->id, 'size_issue'], [$ex->original_order_id, $ex->replacement_order_id, $ex->reason]);
        $this->assertSame(S::AWAITING_RETURN_PRODUCT, $original->fresh()->status);
    }

    public function test_exchange_mode_rejects_an_order_that_is_not_delivered(): void
    {
        $original = $this->order('SHOP-101', S::PROCESSING);

        $this->actingAs($this->admin)->from(route('orders-new.create'))
            ->post(route('orders-new.store'), $this->exchangePayload($original))
            ->assertRedirect(route('orders-new.create'))->assertSessionHas('error');

        $this->assertCount(0, $this->sentTo('/api/v1/orders', 'POST'));
        $this->assertSame(0, Exchange::count());
    }

    public function test_exchange_mode_rejects_an_order_from_another_store(): void
    {
        $other = $this->makeStore('DFOTHER1', 'https://other.test');
        $original = $this->order('OTH-1', S::DELIVERED, ['store_id' => $other->id]);

        $this->actingAs($this->admin)->post(route('orders-new.store'), $this->exchangePayload($original))
            ->assertSessionHas('error');
        $this->assertCount(0, $this->sentTo('/api/v1/orders', 'POST'));
    }

    public function test_an_order_cannot_be_exchanged_twice(): void
    {
        $original = $this->order('SHOP-102', S::DELIVERED);
        $this->actingAs($this->admin)->post(route('orders-new.store'), $this->exchangePayload($original));
        $this->actingAs($this->admin)->post(route('orders-new.store'), $this->exchangePayload($original))
            ->assertSessionHas('error');

        $this->assertSame(1, Exchange::count());
        $this->assertCount(1, $this->sentTo('/api/v1/orders', 'POST'));
    }

    public function test_exchange_mode_validates_required_fields(): void
    {
        $this->actingAs($this->admin)->post(route('orders-new.store'), ['is_exchange' => 1, 'store_id' => $this->store->id])
            ->assertSessionHasErrors(['original_order_id', 'exchange_reason', 'items']);
        $this->assertCount(0, $this->sentTo('/api/v1/orders', 'POST'));
    }

    public function test_store_rejection_shows_the_reason_and_changes_nothing(): void
    {
        $original = $this->order('SHOP-103', S::DELIVERED);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*/api/v1/orders' => Http::response(['message' => 'SKU NEW-1 is out of stock'], 422), '*' => Http::response(['ok' => true])]);

        $this->actingAs($this->admin)->post(route('orders-new.store'), $this->exchangePayload($original))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'SKU NEW-1 is out of stock'));

        $this->assertSame(0, Exchange::count());
        $this->assertSame(S::DELIVERED, $original->fresh()->status);
    }

    public function test_regular_register_order_is_unchanged(): void
    {
        ProductMirror::create(['store_id' => $this->store->id, 'product_id' => 1, 'sku' => 'NEW-1', 'name' => 'New Shirt', 'price' => 1100]);

        $this->actingAs($this->admin)->post(route('orders-new.store'), [
            'store_id' => $this->store->id, 'customer_name' => 'Rafi', 'customer_phone' => '01712345678',
            'shipping_address_line' => 'Road 1', 'shipping_district' => 'Dhaka', 'shipping_city' => 'Dhaka', 'shipping_zone' => 'inside_dhaka',
            'payment_method' => 'cod', 'items' => [['sku' => 'NEW-1', 'qty' => 1]],
        ])->assertRedirect(route('orders-new.index'));

        $d = $this->sentTo('/api/v1/orders', 'POST')[0]->data();
        $this->assertSame('oms_manual', $d['source']);
        $this->assertArrayNotHasKey('is_exchange', $d);
        $this->assertNull(OrderMirror::where('order_number', 'SHOP-NEW-1')->value('exchange_of_order_id'));
        $this->assertSame(0, Exchange::count());
    }

    public function test_lookup_lists_only_delivered_orders_of_the_store(): void
    {
        $this->order('SHOP-200', S::DELIVERED);
        $this->order('SHOP-201', S::PROCESSING);
        $other = $this->makeStore('DFOTHER2', 'https://other2.test');
        $this->order('OTH-2', S::DELIVERED, ['store_id' => $other->id]);

        $rows = $this->actingAs($this->admin)
            ->getJson(route('orders-new.exchange-lookup', ['store_id' => $this->store->id]))->assertOk()->json();

        $this->assertSame(['SHOP-200'], array_column($rows, 'order_number'));
        $this->assertSame('Old Shirt', $rows[0]['items'][0]['name']);
    }

    public function test_order_detail_issue_exchange_still_works(): void
    {
        $original = $this->order('SHOP-300', S::DELIVERED);
        $this->actingAs($this->admin)->post(route('exchanges.open', $original), [
            'reason' => 'defective', 'items' => [['sku' => 'NEW-1', 'qty' => 1]],
        ])->assertRedirect();

        $this->assertSame(1, Exchange::count());
        $this->assertSame(S::AWAITING_RETURN_PRODUCT, $original->fresh()->status);
    }

    // ── Courier: Steadfast exchange parcels ───────────────────────────────

    private function replacement(string $number = 'SHOP-EX-9'): OrderMirror
    {
        $original = $this->order('SHOP-ORIG-'.$number, S::AWAITING_RETURN_PRODUCT);
        return $this->order($number, S::PROCESSING, ['exchange_of_order_id' => $original->id]);
    }

    public function test_steadfast_exchange_order_is_never_auto_booked_as_a_regular_parcel(): void
    {
        $rep = $this->replacement();

        $this->actingAs($this->admin)->post(route('processing.start-packing', $rep))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'exchange order'));

        $this->assertCount(0, $this->sentTo('create_order'));
        $this->assertCount(0, $this->sentTo('/shipments', 'POST'));
        $this->assertSame(0, $rep->consignments()->count());
        $this->assertSame(S::PROCESSING, $rep->fresh()->status);
    }

    public function test_regular_steadfast_order_still_books_through_the_api(): void
    {
        $o = $this->order('SHOP-REG-1', S::PROCESSING);

        $this->actingAs($this->admin)->post(route('processing.start-packing', $o))
            ->assertRedirect(route('labels.single', $o));

        $this->assertCount(1, $this->sentTo('create_order'));
        $this->assertSame('999', (string) $o->consignments()->value('consignment_id'));
    }

    public function test_processing_shows_book_exchange_for_exchange_orders(): void
    {
        $rep = $this->replacement();
        $html = $this->actingAs($this->admin)->get(route('processing.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('processing.exchange-booking', $rep), $html);
        $this->assertStringContainsString('EXCHANGE', $html);

        $this->actingAs($this->admin)->get(route('processing.exchange-booking', $rep))->assertOk()
            ->assertSee('Exchange parcel')->assertSee('name="consignment_id"', false)->assertSee($rep->order_number);
    }

    public function test_verified_consignment_is_saved_and_recorded_on_the_store(): void
    {
        $rep = $this->replacement();

        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $rep), [
            'consignment_id' => '308619779', 'tracking_code' => 'SFR2610XYZ',
        ])->assertRedirect(route('labels.single', $rep));

        $check = $this->sentTo('status_by_cid/308619779', 'GET');
        $this->assertCount(1, $check);
        $this->assertSame('sf-key', $check[0]->header('Api-Key')[0]);

        $cons = $rep->consignments()->firstOrFail();
        $this->assertSame(['steadfast', '308619779', 'SFR2610XYZ', 'booked'],
            [$cons->courier_slug, $cons->consignment_id, $cons->tracking_code, $cons->latest_status]);
        $this->assertSame('manual_exchange', $cons->raw_payload['source']);
        $this->assertEquals(999, $cons->cod_amount);

        $record = $this->sentTo('/orders/'.$rep->order_number.'/shipments', 'POST');
        $this->assertCount(1, $record);
        $this->assertSame('308619779', $record[0]->data()['consignment_id']);
        $this->assertCount(0, $this->sentTo('create_order'));
    }

    public function test_consignment_unknown_to_steadfast_is_not_saved(): void
    {
        $rep = $this->replacement();
        $this->steadfastStatus = ['status' => 404, 'message' => 'Consignment not found'];

        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $rep), ['consignment_id' => '111'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Consignment not found'));
        $this->assertSame(0, CourierConsignment::count());
    }

    public function test_network_failure_does_not_save_the_consignment(): void
    {
        $rep = $this->replacement();
        $this->steadfastStatus = 'down';

        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $rep), ['consignment_id' => '222'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Could not reach Steadfast'));
        $this->assertSame(0, CourierConsignment::count());
    }

    public function test_a_consignment_cannot_be_attached_to_two_orders(): void
    {
        $first = $this->replacement('SHOP-EX-A');
        $second = $this->replacement('SHOP-EX-B');
        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $first), ['consignment_id' => '333']);

        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $second), ['consignment_id' => '333'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'already recorded for order SHOP-EX-A'));
        $this->assertSame(0, $second->consignments()->count());
    }

    public function test_manual_entry_is_refused_for_regular_orders_and_other_couriers(): void
    {
        $regular = $this->order('SHOP-REG-2', S::PROCESSING);
        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $regular), ['consignment_id' => '444'])
            ->assertStatus(422);

        $this->courier = 'pathao';
        $rep = $this->replacement();
        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $rep), ['consignment_id' => '555'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'only for Steadfast exchange orders'));
        $this->assertSame(0, CourierConsignment::count());
    }
}
