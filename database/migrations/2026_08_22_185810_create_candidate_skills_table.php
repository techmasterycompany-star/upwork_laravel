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
    Schema::create('candidate_skills', function (Blueprint $table) {
        $table->id();
        $table->foreignId('candidate_id')->constrained('candidate_profiles')->onDelete('cascade');
        $table->foreignId('skill_id')->constrained()->onDelete('cascade');
        $table->unsignedInteger('years_experience')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('candidate_skills');
}
};
