<?php

namespace App\Models;

use App\Services\Orders\OrderStateMachine;
use Illuminate\Database\Eloquent\Model;

class OrderMirror extends Model
{
    protected $table = 'orders_mirror';
    protected $guarded = [];

    protected $casts = [
        'raw_payload'        => 'array',
        'placed_at'          => 'datetime',
        'updated_at_remote'  => 'datetime',
        'last_synced_at'     => 'datetime',
        'confirmed_at'       => 'datetime',
        'processing_at'      => 'datetime',
        'packed_at'          => 'datetime',
        'dispatched_at'      => 'datetime',
        'shipped_at'         => 'datetime',
        'out_for_delivery_at'=> 'datetime',
        'delivered_at'       => 'datetime',
        'return_pending_at'  => 'datetime',
        'returned_at'        => 'datetime',
        'cancelled_at'       => 'datetime',
        'subtotal'           => 'decimal:2',
        'discount'           => 'decimal:2',
        'shipping_total'     => 'decimal:2',
        'tax'                => 'decimal:2',
        'grand_total'        => 'decimal:2',
        'paid_total'         => 'decimal:2',
    ];

    public const STATUSES = OrderStateMachine::STATUSES;
    public const LABELS   = OrderStateMachine::LABELS;

    public const CANCEL_REASONS = [
        'customer_changed_mind',
        'duplicate_order',
        'unable_to_contact',
        'fraudulent',
        'price_dispute',
        'out_of_stock',
        'other',
    ];

    public const RETURN_REASONS = [
        'customer_refused',
        'customer_unreachable',
        'wrong_address',
        'courier_issue',
        'delivery_failure',
    ];

    public function store()           { return $this->belongsTo(Store::class); }
    public function items()           { return $this->hasMany(OrderItemMirror::class, 'order_id'); }
    public function pickingSessions() { return $this->hasMany(PickingSession::class, 'order_id'); }
    public function packingSessions() { return $this->hasMany(PackingSession::class, 'order_id'); }
    public function shipments()       { return $this->hasMany(ShipmentLog::class, 'order_id'); }
    public function rmas()            { return $this->hasMany(RmaWorkflow::class, 'order_id'); }
    public function consignments()    { return $this->hasMany(CourierConsignment::class, 'order_id'); }
    public function verifications()   { return $this->hasMany(OrderVerification::class, 'order_id')->latest(); }
    public function verifier()        { return $this->belongsTo(User::class, 'verifier_user_id'); }
    public function packer()          { return $this->belongsTo(User::class, 'packer_user_id'); }
    public function placedBy()        { return $this->belongsTo(User::class, 'placed_by_user_id'); }
    public function exchanges()       { return $this->hasMany(Exchange::class, 'original_order_id'); }
    public function exchangeOriginal(){ return $this->belongsTo(self::class, 'exchange_of_order_id'); }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? ucwords(str_replace('_',' ',(string)$this->status));
    }
}
