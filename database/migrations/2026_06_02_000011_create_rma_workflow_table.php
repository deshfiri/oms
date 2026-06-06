<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rma_workflow', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->unsignedBigInteger('return_id_remote')->nullable()->index();
            $t->string('status')->default('requested')->index();
            $t->foreignId('intake_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decision')->nullable();
            $t->string('condition_grade', 2)->nullable();
            $t->text('inspection_notes')->nullable();
            $t->json('photos_json')->nullable();
            $t->string('inbound_tracking_code')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('rma_workflow'); }
};
