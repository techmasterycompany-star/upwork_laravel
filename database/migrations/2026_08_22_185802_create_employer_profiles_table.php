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
    Schema::create('employer_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('company_name');
        $table->text('description')->nullable();
        $table->string('industry')->nullable();
        $table->string('website')->nullable();
        $table->string('company_logo')->nullable();
        $table->unsignedInteger('free_jobs_used')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('employer_profiles');
}
};
