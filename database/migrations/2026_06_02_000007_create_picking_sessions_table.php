<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picking_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->foreignId('picker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('open');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('items_total')->default(0);
            $t->unsignedInteger('items_picked')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['store_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('picking_sessions'); }
};
