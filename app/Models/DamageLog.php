<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageLog extends Model
{
    protected $table = 'damage_log';
    protected $guarded = [];

    protected $casts = [
        'photos_json' => 'array',
        'recorded_at' => 'datetime',
        'posted_to_storefront_at' => 'datetime',
    ];

    public function store() { return $this->belongsTo(Store::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
