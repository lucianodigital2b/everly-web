<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sign in with Apple, end to end from the identity token inwards.
 *
 * The identity token is the only thing standing between a caller and an
 * arbitrary account, so these tests sign real JWTs against a throwaway keypair
 * and serve them through a faked JWKS — verifying the checks actually run,
 * rather than mocking the verifier and asserting nothing.
 */
class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const KID = 'test-apple-key';

    private string $privateKey;

    /** @var array<string, mixed> */
    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        // The verifier caches JWKS for an hour; tests must not inherit each
        // other's keys.
        Cache::flush();

        // Throwaway keypairs committed under tests/Fixtures/apple rather than
        // generated here: openssl_pkey_new() needs an openssl.cnf that plenty of
        // PHP installs (Windows especially) can't locate, while *reading* an
        // existing key has no such dependency. Keeps the suite portable.
        $this->privateKey = $this->fixtureKey('signing-key.pem');

        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privateKey));

        $this->jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => self::KID,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]]];

        config()->set('services.apple.client_ids', [
            'com.get.everly',
            'com.get.everly.dev',
            'com.get.everly.preview',
        ]);

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($this->jwks),
        ]);
    }

    public function test_first_sign_in_creates_the_account_from_the_token(): void
    {
        $response = $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['email' => 'grace@example.com']),
            'user_identifier' => 'apple-sub-1',
            'full_name' => ['givenName' => 'Grace', 'familyName' => 'Hopper'],
            'email' => 'grace@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonPath('user.name', 'Grace Hopper')
            ->assertJsonPath('user.email', 'grace@example.com');

        $this->assertDatabaseHas('users', [
            'apple_id' => 'apple-sub-1',
            'email' => 'grace@example.com',
        ]);
    }

    /**
     * The whole point of the .dev / .preview bundle ids: a build that isn't
     * production still has to be able to sign in.
     */
    public function test_a_token_from_a_dev_build_is_accepted(): void
    {
        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['aud' => 'com.get.everly.dev']),
            'user_identifier' => 'apple-sub-1',
        ])->assertOk();
    }

    public function test_a_token_for_another_application_is_rejected(): void
    {
        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['aud' => 'com.get.clevernote']),
            'user_identifier' => 'apple-sub-1',
        ])->assertStatus(422)->assertJsonValidationErrors('identity_token');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_token_signed_by_someone_else_is_rejected(): void
    {
        // Right `kid`, right claims, wrong key — the signature is the only thing
        // that can catch this.
        $impostorKey = $this->fixtureKey('impostor-key.pem');

        $forged = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.get.everly',
            'sub' => 'apple-sub-1',
            'iat' => time(),
            'exp' => time() + 600,
        ], $impostorKey, 'RS256', self::KID);

        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $forged,
        ])->assertStatus(422)->assertJsonValidationErrors('identity_token');

        $this->assertDatabaseCount('users', 0);
    }

    /** `user_identifier` is client-supplied, so it may never outrank the signed `sub`. */
    public function test_a_user_identifier_that_contradicts_the_token_is_rejected(): void
    {
        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['sub' => 'apple-sub-1']),
            'user_identifier' => 'apple-sub-somebody-else',
        ])->assertStatus(422)->assertJsonValidationErrors('user_identifier');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_returning_users_are_matched_on_the_apple_id_not_the_name(): void
    {
        $existing = User::factory()->create([
            'apple_id' => 'apple-sub-1',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        // A later sign-in: Apple sends no name or email at all.
        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['sub' => 'apple-sub-1'], withEmail: false),
            'user_identifier' => 'apple-sub-1',
        ])->assertOk()->assertJsonPath('user.id', $existing->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Grace Hopper', $existing->fresh()->name);
    }

    public function test_the_authorization_code_is_exchanged_and_the_refresh_token_stored(): void
    {
        $this->configureAppleKey();

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($this->jwks),
            'appleid.apple.com/auth/token' => Http::response(['refresh_token' => 'apple-refresh-abc']),
        ]);

        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(),
            'authorization_code' => 'one-time-code',
            'user_identifier' => 'apple-sub-1',
        ])->assertOk();

        $this->assertSame('apple-refresh-abc', User::first()->apple_refresh_token);

        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'one-time-code'
            && $request['client_id'] === 'com.get.everly');

        $this->assertSame('com.get.everly', User::first()->apple_client_id);
    }

    /**
     * Apple refuses a code redeemed under a different client, so a .dev sign-in
     * has to be exchanged as `com.get.everly.dev` — not as production.
     */
    public function test_the_exchange_uses_the_bundle_id_the_credential_was_issued_to(): void
    {
        $this->configureAppleKey();

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($this->jwks),
            'appleid.apple.com/auth/token' => Http::response(['refresh_token' => 'apple-refresh-dev']),
        ]);

        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(['aud' => 'com.get.everly.dev']),
            'authorization_code' => 'one-time-code',
            'user_identifier' => 'apple-sub-1',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token'
            && $request['client_id'] === 'com.get.everly.dev');

        $this->assertSame('com.get.everly.dev', User::first()->apple_client_id);
    }

    /** Likewise for revocation: the same client that minted the token. */
    public function test_revocation_uses_the_stored_bundle_id(): void
    {
        $this->configureAppleKey();

        Http::fake(['appleid.apple.com/auth/revoke' => Http::response('', 200)]);

        $user = User::factory()->create(['apple_id' => 'apple-sub-1']);
        $user->apple_refresh_token = 'apple-refresh-dev';
        $user->apple_client_id = 'com.get.everly.dev';
        $user->save();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/auth/user')->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/revoke'
            && $request['client_id'] === 'com.get.everly.dev');
    }

    /** A rejected code (a retried sign-in reuses one) must not fail the login. */
    public function test_a_failed_code_exchange_still_signs_the_user_in(): void
    {
        $this->configureAppleKey();

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($this->jwks),
            'appleid.apple.com/auth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->postJson('/api/auth/apple/callback', [
            'identity_token' => $this->identityToken(),
            'authorization_code' => 'already-used',
            'user_identifier' => 'apple-sub-1',
        ])->assertOk();

        $this->assertNull(User::first()->apple_refresh_token);
    }

    public function test_deleting_the_account_revokes_the_apple_grant(): void
    {
        $this->configureAppleKey();

        Http::fake([
            'appleid.apple.com/auth/revoke' => Http::response('', 200),
        ]);

        $user = User::factory()->create(['apple_id' => 'apple-sub-1']);
        $user->apple_refresh_token = 'apple-refresh-abc';
        $user->apple_client_id = 'com.get.everly';
        $user->save();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/user')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('users', 0);

        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/revoke'
            && $request['token'] === 'apple-refresh-abc'
            && $request['token_type_hint'] === 'refresh_token');
    }

    /** Deletion is the user's decision; Apple being unreachable can't block it. */
    public function test_the_account_is_deleted_even_when_apple_revocation_fails(): void
    {
        $this->configureAppleKey();

        Http::fake([
            'appleid.apple.com/auth/revoke' => Http::response(['error' => 'invalid_client'], 400),
        ]);

        $user = User::factory()->create(['apple_id' => 'apple-sub-1']);
        $user->apple_refresh_token = 'apple-refresh-abc';
        $user->apple_client_id = 'com.get.everly';
        $user->save();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/auth/user')
            ->assertOk();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_deleting_an_account_requires_authentication(): void
    {
        $this->deleteJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_the_refresh_token_is_encrypted_at_rest_and_never_serialized(): void
    {
        $user = User::factory()->create(['apple_id' => 'apple-sub-1']);
        $user->apple_refresh_token = 'apple-refresh-abc';
        $user->apple_client_id = 'com.get.everly';
        $user->save();

        $stored = \DB::table('users')->where('id', $user->id)->value('apple_refresh_token');
        $this->assertNotSame('apple-refresh-abc', $stored);
        $this->assertSame('apple-refresh-abc', $user->fresh()->apple_refresh_token);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonMissingPath('apple_refresh_token');
    }

    /** Credentials for the ES256 client secret, without which revocation no-ops. */
    private function configureAppleKey(): void
    {
        config()->set('services.apple.team_id', 'TEAMID1234');
        config()->set('services.apple.key_id', 'KEYID12345');
        config()->set('services.apple.private_key', $this->fixtureKey('client-secret-key.pem'));
    }

    private function fixtureKey(string $name): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/apple/'.$name));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function identityToken(array $overrides = [], bool $withEmail = true): string
    {
        $claims = array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.get.everly',
            'sub' => 'apple-sub-1',
            'iat' => time(),
            'exp' => time() + 600,
        ], $withEmail ? ['email' => 'grace@example.com'] : [], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', self::KID);
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
