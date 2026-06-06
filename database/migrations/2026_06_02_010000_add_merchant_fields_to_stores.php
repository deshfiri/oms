<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->string('dfid')->nullable()->unique()->after('id');
            $t->string('business_name')->nullable()->after('name');
            $t->string('domain_name')->nullable()->after('business_name');
            $t->string('customer_name')->nullable()->after('domain_name');
            $t->string('customer_phone')->nullable()->after('customer_name');
        });
    }
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->dropColumn(['dfid','business_name','domain_name','customer_name','customer_phone']);
        });
    }
};
