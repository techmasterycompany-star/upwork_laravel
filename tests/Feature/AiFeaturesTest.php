<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiFeaturesTest extends TestCase
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

    private function fakeGeminiJsonResponse(array $decodedJson, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode($decodedJson)],
                            ],
                        ],
                    ],
                ],
            ], $status),
        ]);
    }

    private function fakeGeminiChatResponse(string $text, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $text],
                            ],
                        ],
                    ],
                ],
            ], $status),
        ]);
    }

    // ---------------------------------------------------------------
    // Job description generation (employer only)
    // ---------------------------------------------------------------

    public function test_employer_can_generate_a_job_description_draft(): void
    {
        $this->fakeGeminiJsonResponse([
            'description' => 'We are looking for a great engineer.',
            'responsibilities' => ['Write code', 'Review PRs'],
            'requirements' => ['5 years experience', 'PHP knowledge'],
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs/generate-description', [
                'title' => 'Backend Engineer',
                'experience_level' => 'senior',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['draft' => ['title', 'description', 'responsibilities', 'requirements']]);

        Http::assertSentCount(1);
    }

    public function test_candidate_cannot_generate_a_job_description_draft(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/employer/jobs/generate-description', [
                'title' => 'Backend Engineer',
                'experience_level' => 'senior',
            ]);

        $response->assertStatus(403);
    }

    public function test_generating_job_description_requires_valid_experience_level(): void
    {
        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs/generate-description', [
                'title' => 'Backend Engineer',
                'experience_level' => 'expert',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['experience_level']);
    }

    public function test_job_description_generation_returns_502_when_gemini_response_is_malformed(): void
    {
        $this->fakeGeminiJsonResponse([
            'description' => 'Missing the other two keys.',
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs/generate-description', [
                'title' => 'Backend Engineer',
                'experience_level' => 'senior',
            ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    public function test_job_description_generation_returns_502_when_gemini_request_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/employer/jobs/generate-description', [
                'title' => 'Backend Engineer',
                'experience_level' => 'senior',
            ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Cover letter generation (candidate only)
    // ---------------------------------------------------------------

    public function test_candidate_can_generate_a_cover_letter_draft(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        $this->fakeGeminiJsonResponse([
            'cover_letter' => 'Dear hiring manager, I am excited to apply...',
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/generate-cover-letter");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['draft' => ['job_id', 'cover_letter']]);
    }

    public function test_employer_cannot_generate_a_cover_letter_draft(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/generate-cover-letter");

        $response->assertStatus(403);
    }

    public function test_cover_letter_generation_returns_404_without_candidate_profile(): void
    {
        $candidateWithoutProfile = User::factory()->create(['role' => 'candidate']);
        $job = JobListing::factory()->create(['status' => 'approved']);

        $this->fakeGeminiJsonResponse(['cover_letter' => 'Should never get here.']);

        $response = $this->actingAs($candidateWithoutProfile, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/generate-cover-letter");

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Candidate profile not found.']);
    }

    public function test_cover_letter_generation_returns_502_when_gemini_response_is_malformed(): void
    {
        $job = JobListing::factory()->create(['status' => 'approved']);

        $this->fakeGeminiJsonResponse(['unexpected_key' => 'oops']);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson("/api/candidate/jobs/{$job->id}/generate-cover-letter");

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Career chatbot (any authenticated user)
    // ---------------------------------------------------------------

    public function test_guest_cannot_use_the_chatbot(): void
    {
        $response = $this->postJson('/api/chatbot/ask', [
            'message' => 'How do I improve my resume?',
        ]);

        $response->assertStatus(401);
    }

    public function test_candidate_can_ask_the_chatbot_a_question(): void
    {
        $this->fakeGeminiChatResponse('Here are some resume tips...');

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/chatbot/ask', [
                'message' => 'How do I improve my resume?',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'reply' => 'Here are some resume tips...']);
    }

    public function test_employer_can_also_ask_the_chatbot_a_question(): void
    {
        $this->fakeGeminiChatResponse('Here is some interview panel advice...');

        $response = $this->actingAs($this->employer, 'sanctum')
            ->postJson('/api/chatbot/ask', [
                'message' => 'How do I run a good interview?',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_chatbot_accepts_conversation_history(): void
    {
        $this->fakeGeminiChatResponse('Following up on that...');

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/chatbot/ask', [
                'message' => 'What about salary negotiation?',
                'history' => [
                    ['role' => 'user', 'text' => 'How do I improve my resume?'],
                    ['role' => 'model', 'text' => 'Here are some resume tips...'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_chatbot_requires_a_message(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/chatbot/ask', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chatbot_rejects_invalid_history_role(): void
    {
        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/chatbot/ask', [
                'message' => 'Hello',
                'history' => [
                    ['role' => 'system', 'text' => 'Not allowed'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['history.0.role']);
    }

    public function test_chatbot_returns_502_when_gemini_is_unavailable(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($this->candidate, 'sanctum')
            ->postJson('/api/chatbot/ask', [
                'message' => 'How do I improve my resume?',
            ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }
}
