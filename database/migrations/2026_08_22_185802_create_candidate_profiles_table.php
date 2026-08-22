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
    Schema::create('candidate_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->text('bio')->nullable();
        $table->string('portfolio_url')->nullable();
        $table->string('resume')->nullable();
        $table->string('phone')->nullable();
        $table->string('location')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('candidate_profiles');
}
};
