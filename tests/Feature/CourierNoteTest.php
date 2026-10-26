<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Register order → "Courier note": saved on the order apart from the internal
 * note, editable at verification, and sent with the courier booking — in
 * Steadfast's documented `note` field on the direct API, or to the store
 * website's shipment endpoint (which must echo it back to count as forwarded).
 */
class CourierNoteTest extends TestCase
{
    use RefreshDatabase;

    private const NOTE = 'URGENT: Please deliver this parcel as soon as possible.';

    private User $admin;
    private Store $store;
    private array $sent = [];
    private string $courier = 'steadfast';
    /** Store-website shipment response data; [] = the store could not book. */
    private array $shipment = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['couriers.steadfast.api_key' => 'sf-key', 'couriers.steadfast.secret' => 'sf-secret']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);
        $this->store = new Store(['name' => 'Shop', 'business_name' => 'Shop', 'dfid' => 'DFSHOP01',
            'base_url' => 'https://shop.test', 'api_key' => 'k', 'webhook_secret' => 'wh', 'is_active' => true]);
        $this->store->api_secret = 's';
        $this->store->save();
        ProductMirror::create(['store_id' => $this->store->id, 'product_id' => 1, 'sku' => 'NEW-1', 'name' => 'New Shirt', 'price' => 1100]);

        Http::fake(function (HttpRequest $req) {
            $this->sent[] = $req;
            $url = $req->url();
            if (str_contains($url, 'portal.packzy.com/api/v1/create_order')) {
                return Http::response(['status' => 200, 'consignment' => ['consignment_id' => 999, 'tracking_code' => 'SFR999', 'note' => $req->data()['note'] ?? null]]);
            }
            if (str_contains($url, 'portal.packzy.com/api/v1/status_by_cid/')) {
                return Http::response(['status' => 200, 'delivery_status' => 'in_review']);
            }
            if (str_ends_with($url, '/api/v1/settings/couriers')) {
                return Http::response(['ok' => true, 'data' => ['default_courier' => $this->courier]]);
            }
            if ($req->method() === 'POST' && preg_match('#/api/v1/orders$#', $url)) {
                $d = $req->data();
                return Http::response(['data' => [
                    'order_number' => ($d['source'] ?? '') === 'oms_exchange' ? 'SHOP-EX-1' : 'SHOP-NEW-1',
                    'status' => $d['status'] ?? 'pending', 'payment_method' => $d['payment_method'] ?? 'cod',
                    'subtotal' => 1100, 'grand_total' => 1100,
                    'customer' => $d['customer'] ?? [], 'shipping' => $d['shipping'] ?? [],
                    'items' => [['sku' => 'NEW-1', 'name' => 'New Shirt', 'quantity' => 1, 'unit_price' => 1100]],
                ]], 201);
            }
            if ($req->method() === 'POST' && str_contains($url, '/shipments')) {
                return Http::response(['data' => $this->shipment]);
            }
            return Http::response(['ok' => true, 'data' => []]);
        });
    }

    private function sentTo(string $needle, ?string $method = null): array
    {
        return array_values(array_filter($this->sent, fn (HttpRequest $r) =>
            str_contains($r->url(), $needle) && (! $method || $r->method() === $method)));
    }

    private function register(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('orders-new.store'), $over + [
            'store_id' => $this->store->id, 'customer_name' => 'Rafi', 'customer_phone' => '01712345678',
            'shipping_address_line' => 'Road 1', 'shipping_district' => 'Dhaka', 'shipping_city' => 'Dhaka',
            'shipping_zone' => 'inside_dhaka', 'payment_method' => 'cod', 'items' => [['sku' => 'NEW-1', 'qty' => 1]],
            'notes' => 'Customer prefers evening calls', 'courier_note' => self::NOTE,
        ]);
    }

    private function processingOrder(?string $courierNote = self::NOTE, array $extra = []): OrderMirror
    {
        return OrderMirror::create($extra + [
            'store_id' => $this->store->id, 'order_number' => 'SHOP-P-1', 'status' => S::PROCESSING,
            'shipping_name' => 'Rafi', 'shipping_phone' => '01712345678', 'shipping_address_line' => 'Road 1',
            'shipping_city' => 'Dhaka', 'payment_method' => 'cod', 'grand_total' => 1150, 'placed_at' => now(),
            'notes' => 'internal only', 'courier_note' => $courierNote,
        ]);
    }

    // ── Register order ───────────────────────────────────────────────────

    public function test_form_has_a_courier_note_after_the_internal_note(): void
    {
        $html = $this->actingAs($this->admin)->get(route('orders-new.create'))->assertOk()->getContent();
        $internal = strpos($html, 'name="notes"');
        $courier  = strpos($html, 'name="courier_note"');
        $this->assertNotFalse($internal);
        $this->assertNotFalse($courier);
        $this->assertGreaterThan($internal, $courier);
        $this->assertStringContainsString('maxlength="480"', $html);
    }

    public function test_courier_note_is_saved_on_the_order_apart_from_the_internal_note(): void
    {
        $this->register()->assertRedirect(route('orders-new.index'));

        $order = OrderMirror::where('order_number', 'SHOP-NEW-1')->firstOrFail();
        $this->assertSame(self::NOTE, $order->courier_note);

        // The internal note goes to the store exactly as before; the courier note is not mixed in.
        $d = $this->sentTo('/api/v1/orders', 'POST')[0]->data();
        $this->assertSame('Customer prefers evening calls', $d['note']);
        $this->assertStringNotContainsString('URGENT', json_encode($d));
    }

    public function test_courier_note_is_optional_and_limited_to_480_characters(): void
    {
        $this->register(['courier_note' => null])->assertRedirect(route('orders-new.index'));
        $this->assertNull(OrderMirror::where('order_number', 'SHOP-NEW-1')->value('courier_note'));

        $this->register(['courier_note' => str_repeat('a', 481)])->assertSessionHasErrors('courier_note');
    }

    public function test_exchange_order_keeps_its_courier_note(): void
    {
        $original = OrderMirror::create(['store_id' => $this->store->id, 'order_number' => 'SHOP-OLD', 'status' => S::DELIVERED,
            'customer_name' => 'Rafi', 'customer_phone' => '01712345678', 'payment_method' => 'cod', 'placed_at' => now()]);

        $this->actingAs($this->admin)->post(route('orders-new.store'), [
            'is_exchange' => 1, 'store_id' => $this->store->id, 'original_order_id' => $original->id,
            'exchange_reason' => 'size_issue', 'items' => [['sku' => 'NEW-1', 'qty' => 1]], 'courier_note' => self::NOTE,
        ])->assertRedirect(route('orders-new.index'));

        $this->assertSame(self::NOTE, OrderMirror::where('order_number', 'SHOP-EX-1')->value('courier_note'));
    }

    // ── Courier booking ──────────────────────────────────────────────────

    public function test_direct_steadfast_booking_sends_the_note_in_the_note_field(): void
    {
        $o = $this->processingOrder();
        $this->actingAs($this->admin)->post(route('processing.start-packing', $o))->assertRedirect(route('labels.single', $o));

        $create = $this->sentTo('create_order', 'POST');
        $this->assertCount(1, $create);
        $this->assertSame(self::NOTE, $create[0]->data()['note']);
        $this->assertStringNotContainsString('internal only', json_encode($create[0]->data()));

        $raw = $o->consignments()->firstOrFail()->raw_payload;
        $this->assertSame('steadfast_api', $raw['courier_note_via']);
        $this->assertSame(self::NOTE, $raw['courier_note']);
    }

    public function test_order_without_a_courier_note_books_exactly_as_before(): void
    {
        $o = $this->processingOrder(null);
        $this->actingAs($this->admin)->post(route('processing.start-packing', $o))->assertRedirect(route('labels.single', $o));

        $this->assertNull($this->sentTo('create_order', 'POST')[0]->data()['note']);
        $this->assertArrayNotHasKey('courier_note', $this->sentTo('/shipments', 'POST')[0]->data());
        $this->assertArrayNotHasKey('courier_note_via', $o->consignments()->firstOrFail()->raw_payload);
    }

    public function test_store_website_booking_gets_the_note_and_confirms_it_when_echoed(): void
    {
        $this->courier = 'pathao';
        $this->shipment = ['consignment_id' => 'PTH-1', 'tracking_code' => 'PTH-1', 'courier_note' => self::NOTE];
        $o = $this->processingOrder();

        $this->actingAs($this->admin)->post(route('processing.start-packing', $o))->assertRedirect(route('labels.single', $o));

        $this->assertSame(self::NOTE, $this->sentTo('/shipments', 'POST')[0]->data()['courier_note']);
        $this->assertSame('storefront', $o->consignments()->firstOrFail()->raw_payload['courier_note_via']);
        $this->assertCount(0, $this->sentTo('create_order'));
    }

    public function test_store_website_that_does_not_echo_the_note_is_marked_unconfirmed(): void
    {
        $this->courier = 'pathao';
        $this->shipment = ['consignment_id' => 'PTH-2', 'tracking_code' => 'PTH-2'];
        $o = $this->processingOrder();

        $this->actingAs($this->admin)->post(route('processing.start-packing', $o))->assertRedirect(route('labels.single', $o));

        $this->assertSame('storefront_unconfirmed', $o->consignments()->firstOrFail()->raw_payload['courier_note_via']);
        $this->actingAs($this->admin)->get(route('orders.show', $o))->assertOk()
            ->assertSee(self::NOTE)->assertSee('did not confirm forwarding it');
    }

    public function test_steadfast_exchange_parcel_shows_the_note_to_type_in_the_panel(): void
    {
        $original = OrderMirror::create(['store_id' => $this->store->id, 'order_number' => 'SHOP-OLD', 'status' => S::AWAITING_RETURN_PRODUCT, 'placed_at' => now()]);
        $rep = $this->processingOrder(self::NOTE, ['order_number' => 'SHOP-EX-9', 'exchange_of_order_id' => $original->id]);

        $this->actingAs($this->admin)->get(route('processing.exchange-booking', $rep))->assertOk()->assertSee(self::NOTE);
        $this->actingAs($this->admin)->post(route('processing.exchange-booking.store', $rep), ['consignment_id' => '308619779']);

        $this->assertSame('steadfast_panel', $rep->consignments()->firstOrFail()->raw_payload['courier_note_via']);
    }

    // ── Viewing / editing ────────────────────────────────────────────────

    public function test_verification_can_edit_and_clear_the_courier_note(): void
    {
        $o = $this->processingOrder('old note', ['status' => S::PENDING_VERIFICATION]);

        $this->actingAs($this->admin)->get(route('verification.show', $o))->assertOk()
            ->assertSee('name="courier_note"', false)->assertSee('old note');

        $this->actingAs($this->admin)->patch(route('verification.update', $o), ['courier_note' => self::NOTE, 'notes' => 'internal only']);
        $this->assertSame(self::NOTE, $o->fresh()->courier_note);

        // A form without the field (customer details) leaves it alone…
        $this->actingAs($this->admin)->patch(route('verification.update', $o), ['customer_name' => 'Rafi H']);
        $this->assertSame(self::NOTE, $o->fresh()->courier_note);

        // …and clearing the textarea clears it.
        $this->actingAs($this->admin)->patch(route('verification.update', $o), ['courier_note' => '']);
        $this->assertNull($o->fresh()->courier_note);
        $this->assertSame('internal only', $o->fresh()->notes);
    }

    public function test_order_page_shows_note_waiting_for_booking(): void
    {
        $o = $this->processingOrder();
        $this->actingAs($this->admin)->get(route('orders.show', $o))->assertOk()
            ->assertSee('Courier note')->assertSee(self::NOTE)->assertSee('Not sent yet');
    }
}
