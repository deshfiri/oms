<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers_mirror', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('customer_id');
            $t->string('name')->nullable();
            $t->string('email')->nullable()->index();
            $t->string('phone')->nullable()->index();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'customer_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('customers_mirror'); }
};
