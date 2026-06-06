<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LostLog extends Model
{
    protected $table = 'lost_log';
    protected $guarded = [];
    protected $casts = [
        'notes_json'  => 'array',
        'recorded_at' => 'datetime',
    ];

    public const PARTIES = ['warehouse','courier','vendor'];

    public function store()    { return $this->belongsTo(Store::class); }
    public function order()    { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
