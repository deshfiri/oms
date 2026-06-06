<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->string('shipping_postcode', 20)->nullable()->after('shipping_district');
        });
    }
    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropColumn('shipping_postcode');
        });
    }
};
