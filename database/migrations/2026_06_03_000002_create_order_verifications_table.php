<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders_mirror')->cascadeOnDelete();
            $t->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('call_outcome', 40); // contacted, no_answer, busy, voicemail, refused
            $t->string('action', 20)->nullable(); // confirmed | cancelled | edited
            $t->text('summary')->nullable();
            $t->json('changes_json')->nullable(); // diff of what the agent changed
            $t->timestamp('attempted_at');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_verifications'); }
};
