<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->string('refund_status', 20)->nullable()->after('payment_status');
            $t->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            $t->timestamp('refunded_at')->nullable()->after('refund_amount');
            $t->string('refund_note', 255)->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropColumn(['refund_status', 'refund_amount', 'refunded_at', 'refund_note']);
        });
    }
};
