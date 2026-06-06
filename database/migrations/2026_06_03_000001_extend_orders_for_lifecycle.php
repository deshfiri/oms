<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            // assigned warehouse staff + verification CS agent
            $t->foreignId('verifier_user_id')->nullable()->after('paid_total')->constrained('users')->nullOnDelete();
            $t->foreignId('packer_user_id')->nullable()->after('verifier_user_id')->constrained('users')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->after('packer_user_id');

            // Lifecycle timestamps
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('processing_at')->nullable();
            $t->timestamp('packed_at')->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('out_for_delivery_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('return_pending_at')->nullable();
            $t->timestamp('returned_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();

            // Reasons
            $t->string('cancel_reason', 80)->nullable();
            $t->string('return_reason', 80)->nullable();
            $t->text('notes')->nullable();

            // Exchange linkage
            $t->foreignId('exchange_of_order_id')->nullable()
                ->comment('Replacement order points back to the original')
                ->constrained('orders_mirror')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->dropConstrainedForeignId('verifier_user_id');
            $t->dropConstrainedForeignId('packer_user_id');
            $t->dropColumn('warehouse_id');
            $t->dropColumn([
                'confirmed_at','processing_at','packed_at','dispatched_at','shipped_at',
                'out_for_delivery_at','delivered_at','return_pending_at','returned_at',
                'cancelled_at','cancel_reason','return_reason','notes',
            ]);
            $t->dropConstrainedForeignId('exchange_of_order_id');
        });
    }
};
