<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_a_user_and_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sarah',
            'email' => 'sarah@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                'token',
            ])
            ->assertJsonPath('user.email', 'sarah@example.com');

        // The password hash must never reach the client.
        $response->assertJsonMissingPath('user.password');
    }

    public function test_register_rejects_a_duplicate_email_with_the_laravel_error_shape(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Sarah',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sarah@example.com',
            'password' => 'password123',
            'device_name' => 'iphone',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_rejects_a_bad_password(): void
    {
        User::factory()->create([
            'email' => 'sarah@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sarah@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    /**
     * A social-only account has no password; logging in with one must not blow
     * up on the null hash, it must simply fail.
     */
    public function test_login_rejects_a_passwordless_social_account(): void
    {
        User::factory()->create([
            'email' => 'apple@example.com',
            'password' => null,
            'apple_id' => 'apple-sub-123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'apple@example.com',
            'password' => 'anything',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_be_fetched(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->getJson('/api/events')->assertUnauthorized();
        $this->getJson('/api/plans')->assertUnauthorized();
    }

    /**
     * The guard caches the resolved user for the lifetime of a test, so a
     * second request in-process would still look authenticated. What actually
     * has to hold is that the token row is gone — any later request presenting
     * it then 401s, which is what makes the app clear its session.
     */
    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
