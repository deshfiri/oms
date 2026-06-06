<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('location')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('status')->default('open');
            $t->unsignedInteger('items_counted')->default(0);
            $t->unsignedInteger('items_adjusted')->default(0);
            $t->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $t->string('sku')->index();
            $t->integer('expected_qty')->default(0);
            $t->integer('counted_qty')->default(0);
            $t->integer('variance')->default(0);
            $t->text('notes')->nullable();
            $t->timestamp('counted_at')->nullable();
            $t->foreignId('counter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
