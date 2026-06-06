<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingSession extends Model
{
    protected $guarded = [];
    protected $casts = ['packed_at' => 'datetime'];

    public function store() { return $this->belongsTo(Store::class); }
    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function packer() { return $this->belongsTo(User::class, 'packer_user_id'); }
}
