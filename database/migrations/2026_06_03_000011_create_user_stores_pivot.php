<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_stores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id','store_id']);
        });
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->foreignId('placed_by_user_id')->nullable()->after('exchange_of_order_id')
              ->constrained('users')->nullOnDelete()
              ->comment('Set when the order is created from inside OMS (e.g. by a social media manager).');
        });
    }
    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropConstrainedForeignId('placed_by_user_id');
        });
        Schema::dropIfExists('user_stores');
    }
};
