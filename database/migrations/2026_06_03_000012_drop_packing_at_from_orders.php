<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The Packing intermediate status was rolled back to keep the canonical
        // 17-status lifecycle (per docs/newf.md). Orders now go Processing →
        // Packed in one hop; the "packing in progress" UI is driven by the
        // presence of a courier_consignments row, not a separate timestamp.
        if (Schema::hasColumn('orders_mirror', 'packing_at')) {
            Schema::table('orders_mirror', function (Blueprint $t) {
                $t->dropColumn('packing_at');
            });
        }
    }
    public function down(): void
    {
        Schema::table('orders_mirror', function (Blueprint $t) {
            $t->timestamp('packing_at')->nullable()->after('processing_at');
        });
    }
};
