<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickingScan extends Model
{
    protected $guarded = [];
    protected $casts = ['scanned_at' => 'datetime'];

    public function session() { return $this->belongsTo(PickingSession::class, 'picking_session_id'); }
}
