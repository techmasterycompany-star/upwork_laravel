<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Category;
use App\Models\JobListing;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployerTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;
    private User $otherEmployer;
    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->employer()->create();
        $this->otherEmployer = User::factory()->employer()->create();
        $this->candidate = User::factory()->candidate()->create();
    }

    // ---------------------------------------------------------------
    // Authorization / Access Control
    // ---------------------------------------------------------------

    public function test_guest_cannot_access_employer_endpoints(): void
    {
        $response = $this->getJson('/api/employer/profile');

        $response->assertStatus(401);
    }

    public function test_candidate_cannot_access_employer_endpoints(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/employer/profile');

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------

    public function test_employer_can_view_profile(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/profile');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['profile' => ['id', 'user_id', 'company_name']]);
    }

    public function test_employer_can_update_profile(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->putJson('/api/employer/profile', [
                'company_name' => 'Acme Corp',
                'description' => 'We build things.',
                'industry' => 'Software',
                'website' => 'https://acme.example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Employer profile saved successfully.']);

        $this->assertDatabaseHas('employer_profiles', [
            'user_id' => $this->employer->id,
            'company_name' => 'Acme Corp',
            'industry' => 'Software',
        ]);
    }

    public function test_employer_cannot_update_profile_without_company_name(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->putJson('/api/employer/profile', [
                'description' => 'Missing the required field.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_employer_cannot_update_profile_with_invalid_website(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->putJson('/api/employer/profile', [
                'company_name' => 'Acme Corp',
                'website' => 'not-a-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['website']);
    }

    // ---------------------------------------------------------------
    // Job Listings
    // ---------------------------------------------------------------

    public function test_employer_can_create_job_listing(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs', [
                'category_id' => $category->id,
                'title' => 'Backend Engineer',
                'description' => 'Build and maintain our APIs.',
                'work_type' => 'remote',
                'salary_min' => 1000,
                'salary_max' => 2000,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Job listing created and submitted for approval.'])
            ->assertJsonStructure(['job' => ['id', 'title', 'status']]);

        $this->assertDatabaseHas('job_listings', [
            'title' => 'Backend Engineer',
            'status' => 'pending_approval',
            'employer_id' => $this->employer->employerProfile->id,
        ]);
    }

    public function test_employer_cannot_create_job_listing_with_missing_fields(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'title', 'description', 'work_type']);
    }

    public function test_employer_cannot_create_job_listing_without_a_profile(): void
    {
        $employerWithoutProfile = User::factory()->create(['role' => 'employer']);
        $category = Category::factory()->create();

        $response = $this->actingAs($employerWithoutProfile, 'sanctum')
            ->postJson('/api/employer/jobs', [
                'category_id' => $category->id,
                'title' => 'Backend Engineer',
                'description' => 'Build and maintain our APIs.',
                'work_type' => 'remote',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Please complete your employer profile before posting a job.']);
    }

    public function test_creating_a_job_listing_increments_free_jobs_used(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs', [
                'category_id' => $category->id,
                'title' => 'Job One',
                'description' => 'Description',
                'work_type' => 'remote',
            ])->assertStatus(201);

        $this->assertDatabaseHas('employer_profiles', [
            'id' => $this->employer->employerProfile->id,
            'free_jobs_used' => 1,
        ]);
    }

    public function test_employer_blocked_after_free_job_limit_without_subscription(): void
    {
        $category = Category::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->employer, 'sanctum')
                ->postJson('/api/employer/jobs', [
                    'category_id' => $category->id,
                    'title' => "Job {$i}",
                    'description' => 'Description',
                    'work_type' => 'remote',
                ])->assertStatus(201);
        }

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs', [
                'category_id' => $category->id,
                'title' => 'One Too Many',
                'description' => 'Description',
                'work_type' => 'remote',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_employer_with_active_subscription_can_post_beyond_free_limit(): void
    {
        $category = Category::factory()->create();
        $plan = Plan::factory()->create();
        $employerProfile = $this->employer->employerProfile;
        $employerProfile->update(['free_jobs_used' => 3]);

        Subscription::create([
            'employer_id' => $employerProfile->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs', [
                'category_id' => $category->id,
                'title' => 'Bonus Job',
                'description' => 'Description',
                'work_type' => 'remote',
            ]);

        $response->assertStatus(201);
    }

    public function test_employer_can_list_own_jobs_only(): void
    {
        $category = Category::factory()->create();

        JobListing::factory()->count(2)->create([
            'employer_id' => $this->employer->employerProfile->id,
            'category_id' => $category->id,
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/jobs');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('jobs'));
    }

    public function test_employer_can_view_own_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_employer_cannot_view_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/jobs/{$job->id}");

        $response->assertStatus(403);
    }

    public function test_employer_can_update_own_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'title' => 'Old Title',
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->putJson("/api/employer/jobs/{$job->id}", [
                'title' => 'New Title',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Job listing updated successfully.']);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'title' => 'New Title',
        ]);
    }

    public function test_employer_cannot_update_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->putJson("/api/employer/jobs/{$job->id}", [
                'title' => 'Hijacked Title',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'title' => $job->title,
        ]);
    }

    public function test_employer_can_close_own_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/jobs/{$job->id}/close");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Job listing closed successfully.']);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'status' => 'closed',
        ]);
    }

    public function test_employer_cannot_close_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/jobs/{$job->id}/close");

        $response->assertStatus(403);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'status' => 'approved',
        ]);
    }

    // ---------------------------------------------------------------
    // Subscriptions
    // ---------------------------------------------------------------

    public function test_employer_can_list_available_plans(): void
    {
        Plan::factory()->count(2)->create();

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_employer_can_subscribe_to_a_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/subscription', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Subscription created. Proceed to payment to activate it.',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'employer_id' => $this->employer->employerProfile->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);
    }

    public function test_employer_cannot_subscribe_with_invalid_billing_cycle(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/subscription', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'weekly',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing_cycle']);
    }

    public function test_subscribing_cancels_previous_active_subscription(): void
    {
        $plan = Plan::factory()->create();
        $newPlan = Plan::factory()->create();
        $employerProfile = $this->employer->employerProfile;

        $existing = Subscription::create([
            'employer_id' => $employerProfile->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/subscription', [
                'plan_id' => $newPlan->id,
                'billing_cycle' => 'yearly',
            ])->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $existing->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_employer_can_view_current_active_subscription(): void
    {
        $plan = Plan::factory()->create();
        $employerProfile = $this->employer->employerProfile;

        Subscription::create([
            'employer_id' => $employerProfile->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/subscription');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['id', 'status', 'plan']]);
    }

    public function test_employer_can_cancel_active_subscription(): void
    {
        $plan = Plan::factory()->create();
        $employerProfile = $this->employer->employerProfile;

        Subscription::create([
            'employer_id' => $employerProfile->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/subscription/cancel');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Subscription cancelled successfully.']);

        $this->assertDatabaseHas('subscriptions', [
            'employer_id' => $employerProfile->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_employer_cannot_cancel_when_no_active_subscription(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/subscription/cancel');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'No active subscription found.']);
    }

    // ---------------------------------------------------------------
    // Profile logo upload
    // ---------------------------------------------------------------

    public function test_employer_can_upload_profile_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Company logo updated successfully.']);

        $profile = $this->employer->employerProfile->fresh();
        Storage::disk('public')->assertExists($profile->company_logo);
    }

    public function test_employer_uploading_new_logo_deletes_old_one(): void
    {
        Storage::fake('public');

        $oldPath = UploadedFile::fake()->image('old-logo.png')->store('employer_logos', 'public');
        $this->employer->employerProfile->update(['company_logo' => $oldPath]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/profile/logo', [
                'logo' => UploadedFile::fake()->image('new-logo.png'),
            ]);

        $response->assertStatus(200);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_employer_cannot_upload_logo_with_invalid_file_type(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/profile/logo', [
                'logo' => UploadedFile::fake()->create('resume.pdf', 100),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_employer_without_profile_cannot_upload_logo(): void
    {
        Storage::fake('public');

        $employerWithoutProfile = User::factory()->create(['role' => 'employer']);

        $response = $this->actingAs($employerWithoutProfile, 'sanctum')
            ->postJson('/api/employer/profile/logo', [
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Employer profile not found.']);
    }

    // ---------------------------------------------------------------
    // Applications: listing & viewing
    // ---------------------------------------------------------------

    public function test_employer_can_list_applications_for_own_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        Application::factory()->count(2)->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/jobs/{$job->id}/applications");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('applications'));
    }

    public function test_employer_cannot_list_applications_for_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/jobs/{$job->id}/applications");

        $response->assertStatus(403);
    }

    public function test_employer_can_view_single_application_for_own_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/applications/{$application->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['application' => ['id', 'status', 'candidate', 'job']]);
    }

    public function test_employer_cannot_view_application_for_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson("/api/employer/applications/{$application->id}");

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Applications: review / accept / reject
    // ---------------------------------------------------------------

    public function test_employer_can_mark_application_as_under_review(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/review");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Application marked as under review.']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'under_review',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $application->candidate->user->id,
            'type' => 'application_status_changed',
        ]);
    }

    public function test_employer_can_accept_an_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Application accepted.']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $application->candidate->user->id,
            'type' => 'application_status_changed',
            'title' => 'Application accepted',
        ]);
    }

    public function test_employer_can_reject_an_application_with_reason(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/reject", [
                'rejection_reason' => 'Not enough experience.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Application rejected.']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'rejection_reason' => 'Not enough experience.',
        ]);
    }

    public function test_employer_can_reject_an_application_without_reason(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/reject");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'rejected',
        ]);
    }

    public function test_employer_cannot_accept_application_for_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/accept");

        $response->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'submitted',
        ]);
    }

    public function test_employer_cannot_reject_application_for_another_employers_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->otherEmployer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/reject", [
                'rejection_reason' => 'Trying to hijack this.',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'submitted',
        ]);
    }

    public function test_candidate_cannot_access_employer_application_endpoints(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
        ]);

        $application = Application::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->patchJson("/api/employer/applications/{$application->id}/accept");

        $response->assertStatus(403);
    }
}