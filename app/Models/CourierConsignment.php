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
}
