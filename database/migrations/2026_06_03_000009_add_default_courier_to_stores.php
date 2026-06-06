<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->string('default_courier', 20)->default('steadfast')->after('webhook_secret');
            $t->string('outside_dhaka_courier', 20)->nullable()->after('default_courier');
        });
    }
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->dropColumn(['default_courier','outside_dhaka_courier']);
        });
    }
};
