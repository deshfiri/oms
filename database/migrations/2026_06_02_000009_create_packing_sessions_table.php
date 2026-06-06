<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packing_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->foreignId('packer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('weight_kg', 8, 3)->nullable();
            $t->decimal('length_cm', 8, 2)->nullable();
            $t->decimal('width_cm', 8, 2)->nullable();
            $t->decimal('height_cm', 8, 2)->nullable();
            $t->string('courier')->nullable();
            $t->string('tracking_code')->nullable()->index();
            $t->timestamp('packed_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('packing_sessions'); }
};
