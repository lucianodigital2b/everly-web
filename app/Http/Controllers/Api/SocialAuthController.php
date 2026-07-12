<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SocialTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialTokenVerifier $verifier) {}

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
            'user_identifier' => ['sometimes', 'nullable', 'string'],
            'full_name' => ['sometimes', 'nullable', 'array'],
            'full_name.givenName' => ['sometimes', 'nullable', 'string'],
            'full_name.familyName' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
        ]);

        $profile = $this->verifier->verifyApple($data['identity_token']);

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

        return $this->tokenResponse($user);
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
