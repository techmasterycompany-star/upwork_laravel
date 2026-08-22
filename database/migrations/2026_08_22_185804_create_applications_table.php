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
    Schema::create('applications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('job_id')->constrained('job_listings')->onDelete('cascade');
        $table->foreignId('candidate_id')->constrained('candidate_profiles')->onDelete('cascade');
        $table->string('resume')->nullable();
        $table->text('cover_letter')->nullable();
        $table->string('contact_email')->nullable();
        $table->string('contact_phone')->nullable();
        $table->enum('status', ['submitted', 'under_review', 'accepted', 'rejected', 'cancelled'])->default('submitted');
        $table->text('rejection_reason')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('applications');
}
};
