<?php

namespace Tests\Unit;

use App\Models\OrderMirror;
use App\Services\Couriers\ShipmentBooker;
use App\Services\Couriers\SteadfastCourier;
use App\Services\Orders\ExchangeIssuer;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Small rules behind exchange orders and the courier note — no database:
 * which orders can be exchanged, which bookings must be done by hand in the
 * Steadfast panel, and what goes into Steadfast's `note` field.
 */
class ExchangeAndCourierNoteTest extends TestCase
{
    private function order(array $attrs): OrderMirror
    {
        return (new OrderMirror())->forceFill($attrs + ['order_number' => 'ORD-1']);
    }

    // ── Exchange ─────────────────────────────────────────────────────────

    public function test_only_delivered_orders_can_be_exchanged(): void
    {
        $this->assertTrue(ExchangeIssuer::canExchange($this->order(['status' => S::DELIVERED])));
        $this->assertTrue(ExchangeIssuer::canExchange($this->order(['status' => S::PARTIAL_DELIVERY])));
        $this->assertFalse(ExchangeIssuer::canExchange($this->order(['status' => S::PROCESSING])));
        $this->assertFalse(ExchangeIssuer::canExchange($this->order(['status' => S::AWAITING_RETURN_PRODUCT])));
    }

    public function test_store_errors_are_cleaned_for_the_operator(): void
    {
        $this->assertSame('SKU out of stock', ExchangeIssuer::cleanError('{"message":"SKU out of stock"}'));
        $this->assertSame('Server error', ExchangeIssuer::cleanError("<h1>Server\n  error</h1>"));
    }

    public function test_only_steadfast_exchange_orders_need_the_panel(): void
    {
        $exchange = $this->order(['exchange_of_order_id' => 7]);
        $regular  = $this->order(['exchange_of_order_id' => null]);

        $this->assertTrue(ShipmentBooker::needsManualExchangeBooking($exchange, 'steadfast'));
        $this->assertFalse(ShipmentBooker::needsManualExchangeBooking($exchange, 'pathao'));
        $this->assertFalse(ShipmentBooker::needsManualExchangeBooking($regular, 'steadfast'));
    }

    public function test_steadfast_new_parcel_status_counts_as_booked(): void
    {
        $this->assertSame('booked', (new SteadfastCourier())->normalizeStatus('in_review'));
    }

    // ── Courier note → Steadfast `note` ──────────────────────────────────

    private function bookAndGetNote(?string $note): mixed
    {
        config(['couriers.steadfast.api_key' => 'k', 'couriers.steadfast.secret' => 's']);
        $sent = null;
        Http::fake(function (HttpRequest $req) use (&$sent) {
            $sent = $req->data();
            return Http::response(['status' => 200, 'consignment' => ['consignment_id' => 1, 'tracking_code' => 'T1']]);
        });

        (new SteadfastCourier())->bookConsignment(
            $this->order(['shipping_name' => 'Rafi', 'shipping_phone' => '01712345678', 'payment_method' => 'cod', 'grand_total' => 500]),
            ['note' => $note],
        );
        return $sent['note'];
    }

    public function test_courier_note_goes_in_steadfast_note_field(): void
    {
        $this->assertSame('URGENT: deliver today', $this->bookAndGetNote('URGENT: deliver today'));
    }

    public function test_no_courier_note_sends_null(): void
    {
        $this->assertNull($this->bookAndGetNote(null));
        $this->assertNull($this->bookAndGetNote(''));
    }

    public function test_long_note_is_cut_to_steadfast_limit(): void
    {
        $this->assertSame(OrderMirror::COURIER_NOTE_MAX, mb_strlen($this->bookAndGetNote(str_repeat('অ', 600))));
    }
}
