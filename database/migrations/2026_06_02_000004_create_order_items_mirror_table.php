<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items_mirror', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('sku')->nullable()->index();
            $t->string('name');
            $t->string('variant_label')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2)->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_items_mirror'); }
};
