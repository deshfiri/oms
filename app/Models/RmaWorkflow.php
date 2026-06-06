<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RmaWorkflow extends Model
{
    protected $table = 'rma_workflow';
    protected $guarded = [];

    protected $casts = [
        'photos_json' => 'array',
        'opened_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'decided_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUSES = ['requested', 'approved', 'received', 'inspected', 'completed', 'rejected'];

    public function store() { return $this->belongsTo(Store::class); }
    public function order() { return $this->belongsTo(OrderMirror::class, 'order_id'); }
    public function intakeUser() { return $this->belongsTo(User::class, 'intake_user_id'); }
    public function inspector() { return $this->belongsTo(User::class, 'inspector_user_id'); }
}
