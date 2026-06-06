<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('damage_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('sku')->index();
            $t->unsignedInteger('qty')->default(1);
            $t->string('reason');
            $t->json('photos_json')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('recorded_at')->nullable();
            $t->timestamp('posted_to_storefront_at')->nullable();
            $t->unsignedBigInteger('storefront_damage_id')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('damage_log'); }
};
