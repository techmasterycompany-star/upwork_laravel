<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
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

    public function test_guest_cannot_access_wishlist_endpoints(): void
    {
        $response = $this->getJson('/api/candidate/wishlist');

        $response->assertStatus(401);
    }

    public function test_employer_cannot_access_wishlist_endpoints(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/candidate/wishlist');

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Listing
    // ---------------------------------------------------------------

    public function test_candidate_can_list_own_wishlist(): void
    {
        $jobs = JobListing::factory()->count(2)->create(['status' => 'approved']);

        foreach ($jobs as $job) {
            Wishlist::create([
                'candidate_id' => $this->candidate->candidateProfile->id,
                'job_id' => $job->id,
            ]);
        }

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/candidate/wishlist');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('wishlist'));
    }

    public function test_candidate_cannot_see_another_candidates_wishlist_items(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        Wishlist::create([
            'candidate_id' => $this->otherCandidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/candidate/wishlist');

        $response->assertStatus(200);

        $this->assertCount(0, $response->json('wishlist'));
    }

    public function test_candidate_wishlist_is_empty_by_default(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->getJson('/api/candidate/wishlist');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(0, $response->json('wishlist'));
    }

    // ---------------------------------------------------------------
    // Adding
    // ---------------------------------------------------------------

    public function test_candidate_can_add_job_to_wishlist(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Job added to wishlist.']);

        $this->assertDatabaseHas('wishlists', [
            'candidate_id' => $this->candidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_candidate_cannot_add_same_job_to_wishlist_twice(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        Wishlist::create([
            'candidate_id' => $this->candidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Job is already in your wishlist.']);

        $this->assertDatabaseCount('wishlists', 1);
    }

    public function test_candidate_can_wishlist_a_job_regardless_of_its_approval_status(): void
    {
        $job = JobListing::factory()->create(['status' => 'pending_approval']);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(201);
    }

    public function test_adding_nonexistent_job_to_wishlist_returns_404(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/candidate/wishlist/999999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Removing
    // ---------------------------------------------------------------

    public function test_candidate_can_remove_job_from_wishlist(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        Wishlist::create([
            'candidate_id' => $this->candidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->deleteJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Job removed from wishlist.']);

        $this->assertDatabaseMissing('wishlists', [
            'candidate_id' => $this->candidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_removing_a_job_not_in_wishlist_returns_404(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->deleteJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Job is not in your wishlist.']);
    }

    public function test_removing_a_job_does_not_affect_another_candidates_wishlist(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        Wishlist::create([
            'candidate_id' => $this->otherCandidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->deleteJson("/api/candidate/wishlist/{$job->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('wishlists', [
            'candidate_id' => $this->otherCandidate->candidateProfile->id,
            'job_id' => $job->id,
        ]);
    }
}
