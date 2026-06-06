<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];
    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
