<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courier_consignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->string('courier_slug');                // steadfast, pathao, redx, carrybee, manual
            $t->string('courier_name');                // Display label
            $t->string('consignment_id')->nullable();
            $t->string('tracking_code')->nullable()->index();
            $t->string('tracking_url')->nullable();
            $t->string('label_url')->nullable();
            $t->decimal('cod_amount', 12, 2)->default(0);
            $t->decimal('weight_kg', 8, 3)->nullable();
            $t->string('latest_status', 40)->default('booked');
            $t->json('raw_payload')->nullable();
            $t->timestamp('booked_at')->nullable();
            $t->timestamps();
            $t->unique(['courier_slug', 'consignment_id']);
        });

        Schema::create('courier_tracking_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('consignment_id')->constrained('courier_consignments')->cascadeOnDelete();
            $t->string('status', 40);          // picked_up, in_transit, hub_received, out_for_delivery, delivered, delivery_failed, return_initiated, returned, lost
            $t->string('location')->nullable();
            $t->text('remark')->nullable();
            $t->json('raw_payload')->nullable();
            $t->timestamp('happened_at');
            $t->timestamps();
            $t->index(['status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('courier_tracking_events');
        Schema::dropIfExists('courier_consignments');
    }
};
