<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // User-given label ("MacBook Touch ID") — the id itself is opaque.
            $table->string('name');
            // Base64url per the WebAuthn spec, plenty short enough for a
            // varchar — MySQL can't put a unique index on TEXT without a
            // prefix length, and real credential ids run well under this.
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_counter')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
