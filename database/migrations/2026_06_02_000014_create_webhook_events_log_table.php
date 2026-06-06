<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('event')->index();
            $t->string('event_id')->nullable();
            $t->json('payload');
            $t->timestamp('received_at');
            $t->timestamp('processed_at')->nullable();
            $t->string('status')->default('queued');
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['store_id', 'event_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('webhook_events_log'); }
};
