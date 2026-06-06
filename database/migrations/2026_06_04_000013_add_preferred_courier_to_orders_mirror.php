<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            // The courier chosen on the store website for this order (if any).
            // The OMS uses this when booking instead of auto-guessing.
            $t->string('preferred_courier', 30)->nullable()->after('shipping_zone');
        });
    }

    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropColumn('preferred_courier');
        });
    }
};
