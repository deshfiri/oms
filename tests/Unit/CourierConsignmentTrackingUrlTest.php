<?php

namespace Tests\Unit;

use App\Models\CourierConsignment;
use PHPUnit\Framework\TestCase;

/**
 * trackingUrl() points at the courier's official public tracking PAGE — never a
 * fabricated per-parcel deep link, which couriers don't expose and which renders
 * "link invalid/expired". The operator pastes the (copyable) tracking code there.
 */
class CourierConsignmentTrackingUrlTest extends TestCase
{
    public function test_returns_the_courier_tracking_page(): void
    {
        $this->assertSame(
            'https://steadfast.com.bd/tracking',
            (new CourierConsignment(['courier_slug' => 'steadfast', 'tracking_code' => 'SF123']))->trackingUrl()
        );

        $this->assertSame(
            'https://redx.com.bd/track-parcel',
            (new CourierConsignment(['courier_slug' => 'redx', 'tracking_code' => 'RDX9']))->trackingUrl()
        );
    }

    public function test_never_returns_the_fabricated_deep_link(): void
    {
        // A previously-stored …/t/<code> deep link must be ignored — it 404s /
        // shows "expired". We fall back to the courier's tracking page instead.
        $c = new CourierConsignment([
            'courier_slug'  => 'steadfast',
            'tracking_code' => 'SF123',
            'tracking_url'  => 'https://steadfast.com.bd/t/SF123',
        ]);
        $this->assertSame('https://steadfast.com.bd/tracking', $c->trackingUrl());
    }

    public function test_honours_a_genuine_stored_per_parcel_link(): void
    {
        // If a real opaque customer link is ever captured, use it as-is.
        $c = new CourierConsignment([
            'courier_slug' => 'steadfast',
            'tracking_url' => 'https://steadfast.com.bd/tl/Mq_Xk__i_2oE9umifL7Ylpsm3tOOmwId',
        ]);
        $this->assertSame('https://steadfast.com.bd/tl/Mq_Xk__i_2oE9umifL7Ylpsm3tOOmwId', $c->trackingUrl());
    }

    public function test_returns_null_for_couriers_without_a_public_page(): void
    {
        $this->assertNull((new CourierConsignment(['courier_slug' => 'pathao', 'consignment_id' => 'X']))->trackingUrl());
        $this->assertNull((new CourierConsignment(['courier_slug' => 'carrybee', 'tracking_code' => 'X']))->trackingUrl());
        $this->assertNull((new CourierConsignment(['courier_slug' => 'manual', 'consignment_id' => 'X']))->trackingUrl());
    }
}
