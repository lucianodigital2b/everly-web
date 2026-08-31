<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to Apple's token endpoints on behalf of Sign in with Apple.
 *
 * Two jobs, both tied to account deletion:
 *  - exchange the one-time `authorization_code` the app forwards for a refresh
 *    token, which is the only durable handle we get on the user's Apple grant;
 *  - revoke that token when the account is deleted, which App Store guideline
 *    5.1.1(v) requires of any app offering Sign in with Apple.
 *
 * Both calls authenticate with a short-lived ES256 "client secret" JWT signed by
 * the .p8 private key from the Apple Developer portal. Everything here degrades
 * to a no-op when the key isn't configured, so sign-in keeps working on a box
 * that has no credentials — only revocation is lost.
 */
class AppleTokenClient
{
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    private const AUDIENCE = 'https://appleid.apple.com';

    /** Apple caps the client secret's lifetime at 6 months; minutes is plenty. */
    private const SECRET_TTL_SECONDS = 300;

    public function isConfigured(): bool
    {
        return $this->privateKey() !== null
            && (string) config('services.apple.team_id') !== ''
            && (string) config('services.apple.key_id') !== '';
    }

    /**
     * Trades the app's one-time authorization code for a refresh token.
     * Returns null when the exchange isn't possible or Apple refuses — the
     * caller treats that as "no revoke handle", never as a failed login.
     *
     * `$clientId` must be the bundle id the credential was actually issued to
     * (the identity token's `aud`), not simply the production one: Apple rejects
     * a code redeemed under a different client.
     */
    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): ?string
    {
        if (! $this->isConfigured() || $clientId === '') {
            return null;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $this->clientSecret($clientId),
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ]);

            if (! $response->successful()) {
                Log::warning('Apple authorization_code exchange failed.', [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                ]);

                return null;
            }

            $refreshToken = $response->json('refresh_token');

            return is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
        } catch (Throwable $e) {
            Log::warning('Apple authorization_code exchange errored.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Revokes a refresh token, severing the Apple grant. Returns whether Apple
     * confirmed it; the caller decides how much to care.
     */
    public function revokeRefreshToken(string $refreshToken, string $clientId): bool
    {
        if (! $this->isConfigured() || $clientId === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
                'client_id' => $clientId,
                'client_secret' => $this->clientSecret($clientId),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ]);

            if (! $response->successful()) {
                Log::warning('Apple token revocation failed.', [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                ]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Apple token revocation errored.', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Signs a throwaway client secret to prove the .p8 loads and ES256 signing
     * works. This is the *only* offline check available: Apple validates the
     * `code`/`token` before the client credentials, so both /auth/token and
     * /auth/revoke answer `invalid_grant` to everything — even a fabricated team
     * id and bundle id. Whether the key is authorised for a given App ID can
     * only be learned from a real authorization_code.
     *
     * @throws Throwable when the key cannot be loaded or used
     */
    public function assertKeyUsable(string $clientId): void
    {
        $this->clientSecret($clientId);
    }

    /**
     * The ES256 JWT Apple accepts in place of a static client secret. Signed
     * fresh per call — it is cheap, and caching one would only add a way for it
     * to be stale.
     *
     * `sub` is the client the request is for; the key must be authorised for it,
     * meaning every bundle id we ship has to sit in the same Sign in with Apple
     * group as the key's primary App ID.
     */
    private function clientSecret(string $clientId): string
    {
        $now = time();

        return JWT::encode(
            [
                'iss' => (string) config('services.apple.team_id'),
                'iat' => $now,
                'exp' => $now + self::SECRET_TTL_SECONDS,
                'aud' => self::AUDIENCE,
                'sub' => $clientId,
            ],
            (string) $this->privateKey(),
            'ES256',
            (string) config('services.apple.key_id'),
        );
    }

    /**
     * The .p8 contents. `APPLE_PRIVATE_KEY` may hold the PEM inline (handy on
     * hosts with no writable disk, newlines escaped as \n) or
     * `APPLE_PRIVATE_KEY_PATH` may point at the file, resolved relative to the
     * project root when not absolute.
     */
    private function privateKey(): ?string
    {
        $inline = (string) config('services.apple.private_key');

        if ($inline !== '') {
            return str_replace('\n', "\n", $inline);
        }

        $path = (string) config('services.apple.private_key_path');

        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        return is_readable($path) ? (string) file_get_contents($path) : null;
    }
}
