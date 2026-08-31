<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AppleTokenClient;
use App\Services\SocialTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialTokenVerifier $verifier,
        private readonly AppleTokenClient $appleTokens,
    ) {}

    public function google(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'access_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $profile = $this->verifier->verifyGoogle($data['id_token']);

        $user = $this->resolveUser(
            column: 'google_id',
            providerId: $profile['sub'],
            email: $profile['email'],
            name: $profile['name'],
        );

        return $this->tokenResponse($user);
    }

    public function apple(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity_token' => ['required', 'string'],
            'authorization_code' => ['sometimes', 'nullable', 'string'],
            'user_identifier' => ['sometimes', 'nullable', 'string'],
            'full_name' => ['sometimes', 'nullable', 'array'],
            'full_name.givenName' => ['sometimes', 'nullable', 'string'],
            'full_name.familyName' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
        ]);

        $profile = $this->verifier->verifyApple($data['identity_token']);

        // `user_identifier` is client-supplied, so it is only ever a cross-check
        // against the signed `sub` — never a lookup key.
        if (! empty($data['user_identifier']) && $data['user_identifier'] !== $profile['sub']) {
            throw ValidationException::withMessages([
                'user_identifier' => ['The Apple user identifier does not match the token.'],
            ]);
        }

        // Apple sends the name and email only on the first authorization, so we
        // trust the token's `sub` for identity and treat the request body as a
        // best-effort source for the display name.
        $name = trim(implode(' ', array_filter([
            $data['full_name']['givenName'] ?? null,
            $data['full_name']['familyName'] ?? null,
        ])));

        $user = $this->resolveUser(
            column: 'apple_id',
            providerId: $profile['sub'],
            email: $profile['email'] ?? ($data['email'] ?? null),
            name: $name !== '' ? $name : null,
        );

        $this->storeAppleRefreshToken($user, $data['authorization_code'] ?? null, $profile['aud']);

        return $this->tokenResponse($user);
    }

    /**
     * Exchanges the one-time authorization code for a refresh token so account
     * deletion can revoke the Apple grant later (guideline 5.1.1(v)).
     *
     * Deliberately best-effort: a user who cannot sign in is a worse outcome
     * than one whose deletion has to fall back to a manual revoke, and Apple
     * rejects a code that was already redeemed — which is exactly what a retried
     * sign-in sends. An existing token is never overwritten with nothing.
     */
    private function storeAppleRefreshToken(User $user, ?string $authorizationCode, string $clientId): void
    {
        if ($authorizationCode === null || $authorizationCode === '' || $user->apple_refresh_token !== null) {
            return;
        }

        $refreshToken = $this->appleTokens->exchangeAuthorizationCode($authorizationCode, $clientId);

        if ($refreshToken !== null) {
            $user->apple_refresh_token = $refreshToken;
            // Revocation later has to name the same client, so the token and the
            // id it belongs to are stored together or not at all.
            $user->apple_client_id = $clientId;
            $user->save();
        }
    }

    /**
     * Find the user by provider id, then fall back to matching a pre-existing
     * account on the verified email so signing in with Google after registering
     * with a password links the two rather than colliding on the unique email.
     */
    private function resolveUser(string $column, string $providerId, ?string $email, ?string $name): User
    {
        $user = User::where($column, $providerId)->first();

        if (! $user && $email !== null) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $user = new User([
                'name' => $name ?: Str::before((string) $email, '@') ?: 'Everly user',
                // Apple's private relay can withhold the email entirely; keep the
                // column populated with something unique and non-routable.
                'email' => $email ?? $providerId.'@'.$column.'.everly.invalid',
            ]);
        }

        $user->{$column} = $providerId;

        if ($name && $user->name === '') {
            $user->name = $name;
        }

        $user->save();

        return $user;
    }

    private function tokenResponse(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }
}
