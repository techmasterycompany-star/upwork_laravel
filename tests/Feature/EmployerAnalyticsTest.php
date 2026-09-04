<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $employer;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employer = User::factory()->employer()->create();
        $this->candidate = User::factory()->candidate()->create();
    }

    public function test_guest_cannot_access_employer_analytics(): void
    {
        $response = $this->getJson('/api/employer/analytics');

        $response->assertStatus(401);
    }

    public function test_candidate_cannot_access_employer_analytics(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/employer/analytics');

        $response->assertStatus(403);
    }

    public function test_employer_without_profile_gets_404(): void
    {
        $employerWithoutProfile = User::factory()->create(['role' => 'employer']);

        $response = $this->actingAs($employerWithoutProfile, 'sanctum')
            ->getJson('/api/employer/analytics');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Employer profile not found.']);
    }

    public function test_employer_analytics_returns_zeroed_totals_with_no_jobs(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/analytics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'totals' => [
                        'jobs_count' => 0,
                        'total_views' => 0,
                        'total_applications' => 0,
                    ],
                ],
            ]);

        $this->assertCount(0, $response->json('data.jobs'));
    }

    public function test_employer_analytics_totals_up_views_and_applications_across_jobs(): void
    {
        $employerProfile = $this->employer->employerProfile;

        JobListing::factory()->create([
            'employer_id' => $employerProfile->id,
            'views_count' => 10,
            'applications_count' => 2,
        ]);

        JobListing::factory()->create([
            'employer_id' => $employerProfile->id,
            'views_count' => 25,
            'applications_count' => 5,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/analytics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'totals' => [
                        'jobs_count' => 2,
                        'total_views' => 35,
                        'total_applications' => 7,
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.jobs'));
    }

    public function test_employer_analytics_only_counts_own_jobs(): void
    {
        $otherEmployer = User::factory()->employer()->create();

        JobListing::factory()->create([
            'employer_id' => $otherEmployer->employerProfile->id,
            'views_count' => 100,
            'applications_count' => 20,
        ]);

        JobListing::factory()->create([
            'employer_id' => $this->employer->employerProfile->id,
            'views_count' => 5,
            'applications_count' => 1,
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/employer/analytics');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'totals' => [
                        'jobs_count' => 1,
                        'total_views' => 5,
                        'total_applications' => 1,
                    ],
                ],
            ]);
    }
}
