<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The Store model has referenced courier_credentials_enc / courier_enabled
     * since the courier work landed, but no migration ever created them — so
     * saving per-store courier credentials crashed on any database built from
     * the migrations. Guarded so environments that were patched by hand are a
     * no-op.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (! Schema::hasColumn('stores', 'courier_credentials_enc')) {
                $t->text('courier_credentials_enc')->nullable()->after('webhook_secret');
            }
            if (! Schema::hasColumn('stores', 'courier_enabled')) {
                $t->boolean('courier_enabled')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (Schema::hasColumn('stores', 'courier_credentials_enc')) $t->dropColumn('courier_credentials_enc');
            if (Schema::hasColumn('stores', 'courier_enabled')) $t->dropColumn('courier_enabled');
        });
    }
};
