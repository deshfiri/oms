<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickingSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function store() { return $this->belongsTo(Store::class); }
    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function picker() { return $this->belongsTo(User::class, 'picker_user_id'); }
    public function scans() { return $this->hasMany(PickingScan::class); }
}
