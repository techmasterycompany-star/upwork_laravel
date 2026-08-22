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
    Schema::create('wishlists', function (Blueprint $table) {
        $table->id();
        $table->foreignId('candidate_id')->constrained('candidate_profiles')->onDelete('cascade');
        $table->foreignId('job_id')->constrained('job_listings')->onDelete('cascade');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('wishlists');
}
};
