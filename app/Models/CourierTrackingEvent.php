<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierTrackingEvent extends Model
{
    protected $guarded = [];
    protected $casts = [
        'raw_payload' => 'array',
        'happened_at' => 'datetime',
    ];
    public function consignment() { return $this->belongsTo(CourierConsignment::class, 'consignment_id'); }
}
