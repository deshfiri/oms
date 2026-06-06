<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products_mirror', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->string('sku')->index();
            $t->string('name');
            $t->string('type')->default('simple');
            $t->decimal('price', 12, 2)->default(0);
            $t->decimal('sale_price', 12, 2)->nullable();
            $t->integer('stock_quantity')->default(0);
            $t->unsignedInteger('low_stock_threshold')->default(5);
            $t->string('stock_status')->default('in_stock');
            $t->boolean('manage_stock')->default(true);
            $t->string('bin_location')->nullable();
            $t->string('image_url')->nullable();
            $t->timestamp('updated_at_remote')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'sku']);
            $t->index(['store_id', 'product_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('products_mirror'); }
};
