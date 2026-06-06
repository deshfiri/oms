<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lost_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('orders_mirror')->nullOnDelete();
            $t->string('sku')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->string('responsible_party', 20); // warehouse | courier | vendor
            $t->string('reason')->nullable();
            $t->decimal('compensation_amount', 12, 2)->default(0);
            $t->string('compensation_status', 20)->default('pending'); // pending, paid, waived
            $t->json('notes_json')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('recorded_at');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('lost_log'); }
};
