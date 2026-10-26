<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            // Instructions for the courier / rider — kept apart from the internal `notes`.
            $t->string('courier_note', 500)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropColumn('courier_note');
        });
    }
};
