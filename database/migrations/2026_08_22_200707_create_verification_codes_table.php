<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up(): void
{
    Schema::create('verification_codes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->enum('type', ['email_verification', 'password_reset']);
        $table->string('code');
        $table->boolean('is_used')->default(false);
        $table->timestamp('expires_at');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('verification_codes');
}
};
