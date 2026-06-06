<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderVerification extends Model
{
    protected $guarded = [];
    protected $casts = [
        'changes_json' => 'array',
        'attempted_at' => 'datetime',
    ];
    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function agent() { return $this->belongsTo(User::class, 'agent_user_id'); }
}
