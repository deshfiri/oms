<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_product_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('sku');
            $t->decimal('cost_price', 12, 2)->default(0);         // purchase / unit cost ৳
            $t->decimal('daily_storage_rate', 12, 4)->default(0); // ৳ per unit per day
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'sku']);
            $t->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_product_costs');
    }
};
