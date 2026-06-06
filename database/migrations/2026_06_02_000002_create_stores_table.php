<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('base_url');
            $t->string('api_key');
            $t->text('api_secret_enc');
            $t->string('webhook_secret');
            $t->timestamp('last_sync_at')->nullable();
            $t->unsignedBigInteger('last_synced_orders_id')->default(0);
            $t->unsignedBigInteger('last_synced_products_id')->default(0);
            $t->unsignedBigInteger('last_synced_shipments_id')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stores'); }
};
