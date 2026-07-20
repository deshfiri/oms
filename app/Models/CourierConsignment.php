<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierConsignment extends Model
{
    protected $guarded = [];
    protected $casts = [
        'raw_payload' => 'array',
        'booked_at'   => 'datetime',
    ];
    public function order()  { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function events() { return $this->hasMany(CourierTrackingEvent::class, 'consignment_id')->latest('happened_at'); }

    /**
     * The courier's official public tracking PAGE — where the tracking code is
     * looked up.
     *
     * We deliberately do NOT build a per-parcel deep link. Couriers don't return one
     * through their API (Steadfast's create_order gives only the consignment id +
     * tracking code), and the real per-parcel customer link — e.g.
     * steadfast.com.bd/tl/<token> — is an opaque token sent by SMS that can't be
     * reconstructed from the code. A guessed deep link (…/t/<code>) just renders
     * "tracking link invalid/expired", so we link to the tracking page instead and
     * let the operator paste the copyable tracking code.
     *
     * Returns null for couriers with no known public tracking page (a stored
     * tracking_url from the courier, if ever present, still wins).
     */
    public function trackingUrl(): ?string
    {
        // Honour a genuine per-parcel link if the courier/storefront ever supplies
        // one — but never the fabricated …/t/<code> deep link that used to be stored.
        if (! empty($this->tracking_url) && ! str_contains($this->tracking_url, '/t/')) {
            return $this->tracking_url;
        }

        return match ($this->courier_slug) {
            'steadfast' => 'https://steadfast.com.bd/tracking',
            'redx'      => 'https://redx.com.bd/track-parcel',
            default     => null,
        };
    }
}
