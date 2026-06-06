<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemMirror extends Model
{
    protected $table = 'order_items_mirror';
    protected $guarded = [];

    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
}
