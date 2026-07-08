<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_billing_rates', function (Blueprint $t) {
            $t->id();
            // NULL store_id = global default; a store_id = override for that store only
            $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('storage_rate_per_item_per_month', 10, 2)->default(0);
            $t->decimal('packaging_cost_per_item', 10, 2)->default(0);
            $t->timestamps();
            $t->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_billing_rates');
    }
};
