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
    Schema::create('plans', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('job_post_limit')->nullable(); // null = unlimited
        $table->decimal('price_monthly', 10, 2);
        $table->decimal('price_yearly', 10, 2);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('plans');
}
};
