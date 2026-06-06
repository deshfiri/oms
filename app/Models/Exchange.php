<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    protected $guarded = [];
    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    public function original()    { return $this->belongsTo(OrderMirror::class, 'original_order_id'); }
    public function replacement() { return $this->belongsTo(OrderMirror::class, 'replacement_order_id'); }
    public function requester()   { return $this->belongsTo(User::class, 'requested_by'); }
}
