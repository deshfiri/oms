<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->unsignedBigInteger('remote_shipment_id')->nullable();
            $t->string('courier');
            $t->string('tracking_code')->nullable()->index();
            $t->string('consignment_id')->nullable();
            $t->string('status')->default('booked')->index();
            $t->decimal('cod_amount', 12, 2)->default(0);
            $t->timestamp('booked_at')->nullable();
            $t->timestamp('picked_up_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('last_status_at')->nullable();
            $t->string('pod_url')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('shipments_log'); }
};
