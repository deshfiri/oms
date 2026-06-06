<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picking_scans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('picking_session_id')->constrained()->cascadeOnDelete();
            $t->string('sku');
            $t->unsignedInteger('qty_scanned')->default(1);
            $t->timestamp('scanned_at')->nullable();
            $t->foreignId('scanner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('picking_scans'); }
};
