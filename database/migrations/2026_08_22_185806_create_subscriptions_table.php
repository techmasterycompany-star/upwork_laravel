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
    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employer_id')->constrained('employer_profiles')->onDelete('cascade');
        $table->foreignId('plan_id')->constrained()->onDelete('cascade');
        $table->enum('billing_cycle', ['monthly', 'yearly']);
        $table->enum('status', ['pending', 'active', 'cancelled', 'expired'])->default('pending');   
        $table->date('current_period_start');
        $table->date('current_period_end');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('subscriptions');
}
};
