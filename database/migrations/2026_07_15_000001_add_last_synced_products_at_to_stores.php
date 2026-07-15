<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            // Watermark for the products `updated_since` pass — tracked apart
            // from `last_sync_at` (which the orders job also bumps) so the
            // price-change window is never silently narrowed by an order sync.
            $t->timestamp('last_synced_products_at')->nullable()->after('last_synced_products_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->dropColumn('last_synced_products_at');
        });
    }
};
