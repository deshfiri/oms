<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_otp_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('code_hash', 64);   // sha256 hex of the plain OTP
            $t->json('allowed_actions');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();

            $t->unique(['store_id', 'code_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_otp_codes');
    }
};
