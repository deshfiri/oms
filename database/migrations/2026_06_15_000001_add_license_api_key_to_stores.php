<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->string('license_api_key', 80)->nullable()->unique()->after('webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            $t->dropColumn('license_api_key');
        });
    }
};
