<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->string('coupon_code', 60)->nullable()->after('discount');
            $t->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_code');
        });
    }
    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropColumn(['coupon_code','coupon_discount']);
        });
    }
};
