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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('USD');
        $table->enum('gateway', ['paypal', 'stripe']);
        $table->string('gateway_transaction_id')->nullable();
        $table->enum('status', ['completed', 'failed', 'refunded'])->default('completed');
        $table->timestamp('paid_at')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('payments');
}
};
