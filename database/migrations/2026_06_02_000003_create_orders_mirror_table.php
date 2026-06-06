<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders_mirror', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('order_number')->index();
            $t->string('status')->index();
            $t->string('payment_status')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('currency', 8)->default('BDT');

            $t->string('customer_name')->nullable();
            $t->string('customer_email')->nullable();
            $t->string('customer_phone')->nullable();

            $t->string('shipping_name')->nullable();
            $t->string('shipping_phone')->nullable();
            $t->string('shipping_address_line')->nullable();
            $t->string('shipping_area')->nullable();
            $t->string('shipping_city')->nullable();
            $t->string('shipping_district')->nullable();
            $t->string('shipping_zone')->nullable();

            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('shipping_total', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('grand_total', 12, 2)->default(0);
            $t->decimal('paid_total', 12, 2)->default(0);

            $t->timestamp('placed_at')->nullable();
            $t->timestamp('updated_at_remote')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->json('raw_payload')->nullable();
            $t->timestamps();

            $t->unique(['store_id', 'order_number']);
            $t->index(['store_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders_mirror'); }
};
