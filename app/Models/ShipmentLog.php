<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentLog extends Model
{
    protected $table = 'shipments_log';
    protected $guarded = [];

    protected $casts = [
        'booked_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_status_at' => 'datetime',
    ];

    public function store() { return $this->belongsTo(Store::class); }
    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
}
