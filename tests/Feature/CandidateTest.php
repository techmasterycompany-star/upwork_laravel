<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $otherCandidate;
    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->candidate()->create();
        $this->otherCandidate = User::factory()->candidate()->create();
        $this->employer = User::factory()->employer()->create();
    }

    // ---------------------------------------------------------------
    // Authorization / Access Control
    // ---------------------------------------------------------------

    public function test_guest_cannot_access_candidate_endpoints(): void
    {
        $response = $this->getJson('/api/candidate/profile');

        $response->assertStatus(401);
    }

    public function test_employer_cannot_access_candidate_endpoints(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/candidate/profile');

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------

    public function test_candidate_can_view_profile(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/candidate/profile');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['profile' => ['id', 'user_id']]);
    }

    public function test_candidate_can_update_profile(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->putJson('/api/candidate/profile', [
                'bio' => 'Full-stack developer.',
                'portfolio_url' => 'https://portfolio.example.com',
                'phone' => '123456789',
                'location' => 'Cairo, Egypt',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Candidate profile saved successfully.']);

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $this->candidate->id,
            'bio' => 'Full-stack developer.',
            'location' => 'Cairo, Egypt',
        ]);
    }

    public function test_candidate_cannot_update_profile_with_invalid_portfolio_url(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->putJson('/api/candidate/profile', [
                'portfolio_url' => 'not-a-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['portfolio_url']);
    }

    // ---------------------------------------------------------------
    // Applications
    // ---------------------------------------------------------------

    public function test_candidate_can_apply_to_an_approved_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply", [
                'cover_letter' => 'I would love to work on this role.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Application submitted successfully.'])
            ->assertJsonStructure(['application' => ['id', 'job_id', 'status']]);

        $this->assertDatabaseHas('applications', [
            'job_id' => $job->id,
            'candidate_id' => $this->candidate->candidateProfile->id,
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'applications_count' => 1,
        ]);
    }

    public function test_candidate_cannot_apply_twice_to_the_same_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->assertStatus(201);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply");

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'You have already applied to this job.']);
    }

    public function test_candidate_cannot_apply_to_a_non_approved_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply");

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'This job is not open for applications.']);
    }

    public function test_candidate_can_list_own_applications_only(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->assertStatus(201);

        $anotherJob = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->otherCandidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$anotherJob->id}/apply")
            ->assertStatus(201);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/candidate/applications');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('applications'));
    }

    public function test_candidate_can_view_own_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $applicationId = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->json('application.id');

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson("/api/candidate/applications/{$applicationId}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_candidate_cannot_view_another_candidates_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $applicationId = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->json('application.id');

        $response = $this->actingAs($this->otherCandidate, 'sanctum')
            ->getJson("/api/candidate/applications/{$applicationId}");

        $response->assertStatus(403);
    }

    public function test_candidate_can_cancel_a_pending_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $applicationId = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->json('application.id');

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->patchJson("/api/candidate/applications/{$applicationId}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Application cancelled successfully.']);

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'status' => 'cancelled',
        ]);
    }

    public function test_candidate_cannot_cancel_an_already_accepted_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $applicationId = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->json('application.id');

        \App\Models\Application::whereKey($applicationId)->update(['status' => 'accepted']);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->patchJson("/api/candidate/applications/{$applicationId}/cancel");

        $response->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Only pending applications can be cancelled.']);
    }

    public function test_candidate_cannot_cancel_another_candidates_application(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $applicationId = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/apply")
            ->json('application.id');

        $response = $this->actingAs($this->otherCandidate, 'sanctum')
            ->patchJson("/api/candidate/applications/{$applicationId}/cancel");

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Public Job Search
    // ---------------------------------------------------------------

    public function test_public_job_listing_only_shows_approved_jobs(): void
    {
        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->getJson('/api/jobs');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('jobs.data'));
    }

    public function test_public_job_search_filters_by_keyword(): void
    {
        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'title' => 'Senior Laravel Developer',
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'title' => 'Marketing Manager',
        ]);

        $response = $this->getJson('/api/jobs/search?keyword=Laravel');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('jobs.data'));
        $this->assertSame('Senior Laravel Developer', $response->json('jobs.data.0.title'));
    }

    public function test_public_job_search_filters_by_category(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'category_id' => $category->id,
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->getJson("/api/jobs/search?category_id={$category->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('jobs.data'));
    }

    public function test_public_job_search_filters_by_work_type(): void
    {
        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'work_type' => 'remote',
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'work_type' => 'onsite',
        ]);

        $response = $this->getJson('/api/jobs/search?work_type=remote');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('jobs.data'));
    }

    public function test_public_job_search_rejects_invalid_work_type(): void
    {
        $response = $this->getJson('/api/jobs/search?work_type=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['work_type']);
    }

    public function test_public_job_show_increments_views_count(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'approved',
            'views_count' => 0,
        ]);

        $response = $this->getJson("/api/jobs/{$job->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'views_count' => 1,
        ]);
    }

    public function test_public_job_show_returns_404_for_non_approved_job(): void
    {
        $job = JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->getJson("/api/jobs/{$job->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Job not found.']);
    }
}
