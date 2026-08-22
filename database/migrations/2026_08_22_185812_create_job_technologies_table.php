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
    Schema::create('job_technologies', function (Blueprint $table) {
        $table->id();
        $table->foreignId('job_id')->constrained('job_listings')->onDelete('cascade');
        $table->foreignId('technology_id')->constrained()->onDelete('cascade');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('job_technologies');
}
};
