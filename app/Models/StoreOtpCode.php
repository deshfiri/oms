<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOtpCode extends Model
{
    protected $fillable = ['store_id', 'code_hash', 'allowed_actions', 'expires_at', 'used_at', 'created_by'];

    protected $casts = [
        'allowed_actions' => 'array',
        'expires_at'      => 'datetime',
        'used_at'         => 'datetime',
    ];

    public function store() { return $this->belongsTo(Store::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function isUsed(): bool    { return $this->used_at !== null; }
    public function isExpired(): bool { return $this->expires_at !== null && $this->expires_at->isPast(); }
    public function isValid(): bool   { return ! $this->isUsed() && ! $this->isExpired(); }

    public function statusLabel(): string
    {
        if ($this->isUsed())    return 'Used';
        if ($this->isExpired()) return 'Expired';
        return 'Valid';
    }
}
