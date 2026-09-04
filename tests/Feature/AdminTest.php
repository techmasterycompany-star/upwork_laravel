<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\EmployerProfile;
use App\Models\JobListing;
use App\Models\Plan;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employer;
    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->admin = User::factory()->admin()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->employer = User::factory()->employer()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->candidate = User::factory()->candidate()->create([
            'password' => Hash::make('password123'),
        ]);
    }

    // ---------------------------------------------------------------
    // Authorization / Access Control
    // ---------------------------------------------------------------

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->getJson('/api/admin/categories');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $response = $this->getJson('/api/admin/categories');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------

    public function test_admin_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/categories');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description']]]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Web Development',
                'description' => 'Web development jobs',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Category created successfully.'])
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description']]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Web Development',
            'slug' => 'web-development',
        ]);
    }

    public function test_admin_cannot_create_duplicate_category(): void
    {
        Category::create([
            'name' => 'Web Development',
            'slug' => 'web-development',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Web Development',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::create([
            'name' => 'Web Development',
            'slug' => 'web-development',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", [
                'name' => 'Backend Development',
                'description' => 'Backend development jobs',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Category updated successfully.']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Backend Development',
            'slug' => 'backend-development',
        ]);
    }

    public function test_admin_can_delete_category(): void
    {
        $category = Category::create([
            'name' => 'Web Development',
            'slug' => 'web-development',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Category deleted successfully.']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ---------------------------------------------------------------
    // Technologies
    // ---------------------------------------------------------------

    public function test_admin_can_list_technologies(): void
    {
        Technology::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/technologies');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_create_technology(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/technologies', [
                'name' => 'Laravel',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Technology created successfully.'])
            ->assertJsonStructure(['data' => ['id', 'name', 'slug']]);

        $this->assertDatabaseHas('technologies', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    public function test_admin_cannot_create_duplicate_technology(): void
    {
        Technology::create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/technologies', [
                'name' => 'Laravel',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_technology(): void
    {
        $technology = Technology::create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/technologies/{$technology->id}", [
                'name' => 'Laravel 11',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Technology updated successfully.']);

        $this->assertDatabaseHas('technologies', [
            'id' => $technology->id,
            'name' => 'Laravel 11',
            'slug' => 'laravel-11',
        ]);
    }

    public function test_admin_can_delete_technology(): void
    {
        $technology = Technology::create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/technologies/{$technology->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Technology deleted successfully.']);

        $this->assertDatabaseMissing('technologies', ['id' => $technology->id]);
    }

    // ---------------------------------------------------------------
    // Job Approval
    // ---------------------------------------------------------------

    public function test_admin_can_list_pending_jobs(): void
    {
        $employer = User::factory()->employer()->create();
        JobListing::factory()->count(3)->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        JobListing::factory()->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/jobs/pending');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'employer']]]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_approve_pending_job(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/jobs/{$job->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Job approved successfully.',
            ])
            ->assertJsonStructure(['data' => ['id', 'status']]);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'status' => 'approved',
            'rejection_reason' => null,
        ]);
    }

    public function test_admin_cannot_approve_non_pending_job(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/jobs/{$job->id}/approve");

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_admin_can_reject_pending_job(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/jobs/{$job->id}/reject", [
                'rejection_reason' => 'Inappropriate content',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Job rejected successfully.']);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'status' => 'rejected',
            'rejection_reason' => 'Inappropriate content',
        ]);
    }

    public function test_admin_can_reject_job_without_reason(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create([
            'employer_id' => $employer->employerProfile->id,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/jobs/{$job->id}/reject");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'status' => 'rejected',
        ]);
    }

    // ---------------------------------------------------------------
    // User Management
    // ---------------------------------------------------------------

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(5)->candidate()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'is_blocked']]]);

        // At least 5 created candidates + 1 admin + 1 employer + 1 original candidate
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_admin_can_block_user(): void
    {
        $user = User::factory()->candidate()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/block");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'User blocked successfully.']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_blocked' => true,
        ]);
    }

    public function test_admin_can_unblock_user(): void
    {
        $user = User::factory()->candidate()->blocked()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/unblock");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'User unblocked successfully.']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_blocked' => false,
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->candidate()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ---------------------------------------------------------------
    // Comment Moderation
    // ---------------------------------------------------------------

    public function test_admin_can_list_comments(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create(['employer_id' => $employer->employerProfile->id]);
        $candidates = User::factory()->count(2)->candidate()->create();

        foreach ($candidates as $candidate) {
            Comment::factory()->create([
                'job_id' => $job->id,
                'user_id' => $candidate->id,
            ]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/comments');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'content', 'user', 'job']]]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_hide_comment(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create(['employer_id' => $employer->employerProfile->id]);
        $candidate = User::factory()->candidate()->create();
        $comment = Comment::factory()->create([
            'job_id' => $job->id,
            'user_id' => $candidate->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/comments/{$comment->id}/hide");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Comment hidden successfully.']);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'is_approved' => false,
        ]);
    }

    public function test_admin_can_delete_comment(): void
    {
        $employer = User::factory()->employer()->create();
        $job = JobListing::factory()->create(['employer_id' => $employer->employerProfile->id]);
        $candidate = User::factory()->candidate()->create();
        $comment = Comment::factory()->create([
            'job_id' => $job->id,
            'user_id' => $candidate->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Comment removed successfully.']);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    // ---------------------------------------------------------------
    // Audit Logs
    // ---------------------------------------------------------------

    public function test_admin_can_list_audit_logs(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data']);
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function test_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_users',
                        'total_employers',
                        'total_candidates',
                        'blocked_users',
                        'total_jobs',
                        'pending_jobs',
                        'approved_jobs',
                        'rejected_jobs',
                        'total_applications',
                        'accepted_applications',
                        'rejected_applications',
                    ],
                    'recent_activity',
                ],
            ]);
    }

    // ---------------------------------------------------------------
    // Plans
    // ---------------------------------------------------------------

    public function test_admin_can_list_plans(): void
    {
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'price_monthly',
                        'price_yearly',
                        'job_post_limit',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_plan(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/plans', [
                'name' => 'Premium Plan',
                'price_monthly' => 99.99,
                'price_yearly' => 999.99,
                'job_post_limit' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Plan created successfully.'])
            ->assertJsonStructure(['data' => ['id', 'name']]);

        $this->assertDatabaseHas('plans', [
            'name' => 'Premium Plan',
            'price_monthly' => 99.99,
        ]);
    }

    public function test_admin_can_update_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Updated Plan',
                'price_monthly' => 149.99,
                'price_yearly' => 1499.99,
                'job_post_limit' => 20,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Plan updated successfully.']);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan',
            'price_monthly' => 149.99,
        ]);
    }

    public function test_admin_can_delete_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Plan deleted successfully.']);

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}