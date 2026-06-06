<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('original_order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->foreignId('replacement_order_id')->nullable()->constrained('orders_mirror')->nullOnDelete();
            $t->string('reason', 80);                 // size_issue, defective, wrong_item, customer_change_of_mind
            $t->text('notes')->nullable();
            $t->string('status', 40)->default('requested'); // requested, processing, awaiting_return, returned, restocked, damaged, completed, cancelled
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('requested_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('exchanges'); }
};
