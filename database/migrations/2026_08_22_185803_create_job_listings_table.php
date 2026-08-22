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
    Schema::create('job_listings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employer_id')->constrained('employer_profiles')->onDelete('cascade');
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('description');
        $table->text('responsibilities')->nullable();
        $table->text('requirements')->nullable();
        $table->string('location')->nullable();
        $table->enum('work_type', ['remote', 'onsite', 'hybrid'])->default('onsite');
        $table->decimal('salary_min', 10, 2)->nullable();
        $table->decimal('salary_max', 10, 2)->nullable();
        $table->string('experience_level')->nullable();
        $table->date('application_deadline')->nullable();
        $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'expired', 'closed'])->default('draft');
        $table->text('rejection_reason')->nullable();
        $table->unsignedInteger('views_count')->default(0);
        $table->unsignedInteger('applications_count')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('job_listings');
}
};
