<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private User $otherCandidate;

    private User $employer;

    private JobListing $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->candidate()->create();
        $this->otherCandidate = User::factory()->candidate()->create();
        $this->employer = User::factory()->employer()->create();
        $this->job = JobListing::factory()->create(['status' => 'approved']);
    }

    // ---------------------------------------------------------------
    // Authorization / Access Control
    // ---------------------------------------------------------------

    public function test_guest_cannot_post_a_comment(): void
    {
        $response = $this->postJson("/api/jobs/{$this->job->id}/comments", [
            'content' => 'Looks like a great opportunity!',
        ]);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Posting
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_post_a_comment(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/jobs/{$this->job->id}/comments", [
                'content' => 'Is this role still open?',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Comment posted successfully.'])
            ->assertJsonStructure(['comment' => ['id', 'content', 'user']]);

        $this->assertDatabaseHas('comments', [
            'job_id' => $this->job->id,
            'user_id' => $this->candidate->id,
            'content' => 'Is this role still open?',
            'is_approved' => 1,
        ]);
    }

    public function test_employer_can_also_post_a_comment(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson("/api/jobs/{$this->job->id}/comments", [
                'content' => 'Thanks for applying, we will review soon.',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comments', [
            'job_id' => $this->job->id,
            'user_id' => $this->employer->id,
        ]);
    }

    public function test_cannot_post_a_comment_without_content(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/jobs/{$this->job->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_cannot_post_a_comment_exceeding_max_length(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/jobs/{$this->job->id}/comments", [
                'content' => str_repeat('a', 2001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_posting_comment_on_nonexistent_job_returns_404(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/jobs/999999/comments', [
                'content' => 'Hello?',
            ]);

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Updating
    // ---------------------------------------------------------------

    public function test_user_can_update_own_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->candidate->id,
            'content' => 'Old content',
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'content' => 'Updated content',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Comment updated successfully.']);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Updated content',
        ]);
    }

    public function test_user_cannot_update_another_users_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->otherCandidate->id,
            'content' => 'Original content',
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'content' => 'Hijacked content',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Original content',
        ]);
    }

    public function test_cannot_update_comment_without_content(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->candidate->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    // ---------------------------------------------------------------
    // Deleting
    // ---------------------------------------------------------------

    public function test_user_can_delete_own_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->candidate->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Comment deleted successfully.']);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->otherCandidate->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'deleted_at' => null]);
    }

    // ---------------------------------------------------------------
    // Reporting
    // ---------------------------------------------------------------

    public function test_user_can_report_a_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->otherCandidate->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report", [
                'reason' => 'Spam content.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Comment reported. Our team will review it.']);

        $this->assertDatabaseHas('comment_reports', [
            'comment_id' => $comment->id,
            'reported_by' => $this->candidate->id,
            'reason' => 'Spam content.',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_report_a_comment_without_a_reason(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->otherCandidate->id,
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report");

        $response->assertStatus(201);
    }

    public function test_user_cannot_report_the_same_comment_twice(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->otherCandidate->id,
        ]);

        $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report", [
                'reason' => 'Spam content.',
            ])->assertStatus(201);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report", [
                'reason' => 'Still spam.',
            ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'You have already reported this comment.']);

        $this->assertDatabaseCount('comment_reports', 1);
    }

    public function test_different_users_can_each_report_the_same_comment(): void
    {
        $comment = Comment::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->employer->id,
        ]);

        $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report")
            ->assertStatus(201);

        $this->actingAs($this->otherCandidate, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/report")
            ->assertStatus(201);

        $this->assertDatabaseCount('comment_reports', 2);
    }
}
