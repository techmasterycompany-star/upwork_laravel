<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Register
    // ---------------------------------------------------------------

    public function test_employer_can_register(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Youssef Tarek',
            'email' => 'youssef@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employer',
            'company_name' => 'Youssef Tech Solutions',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'youssef@example.com',
            'role' => 'employer',
        ]);

        $user = User::where('email', 'youssef@example.com')->first();
        $this->assertDatabaseHas('employer_profiles', [
            'user_id' => $user->id,
            'company_name' => 'Youssef Tech Solutions',
        ]);

        Mail::assertSent(VerificationCodeMail::class);
    }

    public function test_candidate_can_register(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Yomna Ali',
            'email' => 'yomna@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'candidate',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $user = User::where('email', 'yomna@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
        ]);

        Mail::assertSent(VerificationCodeMail::class);
    }

    public function test_employer_registration_requires_company_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Youssef Tarek',
            'email' => 'youssef3@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'candidate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'short@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
            'role' => 'candidate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_fails_with_invalid_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'badrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_registration_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'mismatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'role' => 'candidate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'candidate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ---------------------------------------------------------------
    // Email verification
    // ---------------------------------------------------------------

    public function test_user_can_verify_email_with_correct_code(): void
    {
        $user = User::factory()->candidate()->unverified()->create();
        $verification = VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($verification->fresh()->is_used);
    }

    public function test_email_verification_fails_with_wrong_code(): void
    {
        $user = User::factory()->candidate()->unverified()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_fails_with_expired_code(): void
    {
        $user = User::factory()->candidate()->unverified()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'code' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
    }

    public function test_email_verification_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/verify-email', [
            'email' => 'nobody@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'User not found.']);
    }

    public function test_email_verification_fails_when_no_code_was_ever_requested(): void
    {
        $user = User::factory()->candidate()->unverified()->create();

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Verification code not found.']);
    }

    // ---------------------------------------------------------------
    // Login
    // ---------------------------------------------------------------

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->candidate()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->candidate()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_login_fails_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->candidate()->blocked()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    /**
     * Documents current behaviour: login does not require a verified email.
     * If this is ever meant to change, this test should fail loudly instead
     * of the gap going unnoticed.
     */
    public function test_unverified_user_can_still_login(): void
    {
        $user = User::factory()->candidate()->unverified()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Current user / logout
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_fetch_their_own_data(): void
    {
        $user = User::factory()->candidate()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200)->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_guest_cannot_fetch_current_user(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->candidate()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logged_out_token_can_no_longer_be_used(): void
    {
        $user = User::factory()->candidate()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/logout');

        // Laravel's auth guard caches the resolved user on the guard instance
        // for the lifetime of the test's container. Force it to re-resolve
        // from the (now-deleted) token instead of reusing the cached user
        // from the request above, so this reflects what a real second
        // request would see.
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/user');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Forgot / reset password
    // ---------------------------------------------------------------

    public function test_user_can_request_password_reset_code(): void
    {
        Mail::fake();
        $user = User::factory()->candidate()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'type' => 'password_reset',
        ]);
        Mail::assertSent(ResetPasswordCodeMail::class);
    }

    public function test_forgot_password_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_user_can_verify_reset_code(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_verify_reset_code_fails_with_wrong_code(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Invalid reset code.']);
    }

    public function test_verify_reset_code_fails_with_expired_code(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Reset code expired.']);
    }

    public function test_verify_reset_code_fails_when_already_used(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
            'is_used' => true,
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Reset code already used.']);
    }

    public function test_verify_reset_code_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'nobody@example.com',
            'code' => '654321',
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'User not found.']);
    }

    public function test_user_can_reset_password_with_valid_code(): void
    {
        $user = User::factory()->candidate()->create([
            'password' => Hash::make('old-password'),
        ]);
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '654321',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password123',
        ]);
        $loginResponse->assertStatus(200);
    }

    public function test_reset_password_fails_when_code_already_used(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
            'is_used' => true,
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '654321',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
    }

    public function test_reset_password_fails_with_wrong_code(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Invalid reset code.']);
    }

    public function test_reset_password_fails_with_expired_code(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '654321',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Reset code expired.']);
    }

    public function test_reset_password_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'nobody@example.com',
            'code' => '654321',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'User not found.']);
    }

    public function test_reset_password_fails_without_matching_confirmation(): void
    {
        $user = User::factory()->candidate()->create();
        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '654321',
            'password' => 'new-password123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ---------------------------------------------------------------
    // LinkedIn OAuth
    // ---------------------------------------------------------------

    public function test_linkedin_redirect_returns_an_authorization_url(): void
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(
            redirect('https://www.linkedin.com/oauth/v2/authorization?client_id=test')
        );

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/redirect');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['url']);

        $this->assertStringContainsString('linkedin.com', $response->json('url'));
    }

    public function test_new_candidate_account_is_created_from_linkedin_callback(): void
    {
        $linkedinUser = \Mockery::mock();
        $linkedinUser->shouldReceive('getEmail')->andReturn('newperson@example.com');
        $linkedinUser->shouldReceive('getName')->andReturn('New Person');
        $linkedinUser->shouldReceive('getNickname')->andReturn('newperson');
        $linkedinUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($linkedinUser);

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/callback');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'LinkedIn login successful.'])
            ->assertJsonStructure(['token', 'token_type', 'user', 'linkedin_profile']);

        $user = User::where('email', 'newperson@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('candidate', $user->role);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_existing_user_logs_in_via_linkedin_callback_without_duplicating(): void
    {
        $existing = User::factory()->candidate()->create(['email' => 'already@example.com']);

        $linkedinUser = \Mockery::mock();
        $linkedinUser->shouldReceive('getEmail')->andReturn('already@example.com');
        $linkedinUser->shouldReceive('getName')->andReturn('Already Registered');
        $linkedinUser->shouldReceive('getNickname')->andReturn(null);
        $linkedinUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($linkedinUser);

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/callback');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(1, User::where('email', 'already@example.com')->count());
        $this->assertSame($existing->id, $response->json('user.id'));
    }

    public function test_blocked_user_cannot_login_via_linkedin(): void
    {
        User::factory()->candidate()->blocked()->create(['email' => 'blocked@example.com']);

        $linkedinUser = \Mockery::mock();
        $linkedinUser->shouldReceive('getEmail')->andReturn('blocked@example.com');
        $linkedinUser->shouldReceive('getName')->andReturn('Blocked Person');
        $linkedinUser->shouldReceive('getNickname')->andReturn(null);
        $linkedinUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($linkedinUser);

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/callback');

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Your account is blocked.']);
    }

    public function test_linkedin_callback_fails_when_no_email_is_returned(): void
    {
        $linkedinUser = \Mockery::mock();
        $linkedinUser->shouldReceive('getEmail')->andReturn(null);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($linkedinUser);

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/callback');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'LinkedIn did not return an email address for this account.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_linkedin_callback_fails_when_socialite_throws(): void
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andThrow(new \Exception('invalid state'));

        Socialite::shouldReceive('driver')->with('linkedin')->once()->andReturn($provider);

        $response = $this->getJson('/api/auth/linkedin/callback');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'LinkedIn authentication failed. Please try again.',
            ]);
    }
}