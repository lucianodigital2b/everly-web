<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AppleTokenClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->tokenResponse($user, $request, status: 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Social-only accounts have a null password; Hash::check on null would
        // throw, so guard the whole branch rather than just a bad password.
        if (! $user || $user->password === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return $this->tokenResponse($user, $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Permanently deletes the account. Required by App Store guideline 5.1.1(v),
     * which also requires revoking the Sign in with Apple grant — so Apple is
     * told first, while the refresh token still exists to tell it with.
     *
     * Events and their photos disappear with the user via the cascading foreign
     * keys on `events.user_id` and `event_photos.event_id`.
     */
    public function destroy(Request $request, AppleTokenClient $appleTokens): JsonResponse
    {
        $user = $request->user();

        if ($user->apple_refresh_token !== null && $user->apple_client_id !== null) {
            // A failed revoke must not strand the user with an account they
            // asked to delete; it is logged inside the client and we continue.
            $appleTokens->revokeRefreshToken($user->apple_refresh_token, $user->apple_client_id);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    private function tokenResponse(User $user, Request $request, int $status = 200): JsonResponse
    {
        $device = $request->string('device_name')->toString() ?: 'mobile';

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($device)->plainTextToken,
        ], $status);
    }
}
