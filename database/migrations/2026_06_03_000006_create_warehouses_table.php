<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('code', 32);
            $t->string('name');
            $t->string('address')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['store_id', 'code']);
        });

        Schema::create('warehouse_stock', function (Blueprint $t) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->string('sku');
            $t->unsignedInteger('available')->default(0);
            $t->unsignedInteger('reserved')->default(0);
            $t->unsignedInteger('in_transit')->default(0);
            $t->unsignedInteger('returned')->default(0);
            $t->unsignedInteger('damaged')->default(0);
            $t->timestamps();
            $t->unique(['warehouse_id', 'sku']);
            $t->index('sku');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock');
        Schema::dropIfExists('warehouses');
    }
};
