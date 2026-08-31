<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifies the identity tokens the app forwards from Google / Apple.
 *
 * These tokens are the only thing standing between a caller and an arbitrary
 * account, so they are verified against the provider's signing keys and the
 * audience is checked against our own client id. An unverified decode here
 * would let anyone mint a token for any email.
 */
class SocialTokenVerifier
{
    private const APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';

    private const APPLE_ISSUER = 'https://appleid.apple.com';

    private const GOOGLE_KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @return array{sub: string, email: string|null, name: string|null}
     */
    public function verifyGoogle(string $idToken): array
    {
        $claims = $this->decode($idToken, self::GOOGLE_KEYS_URL, 'google', $this->audiences(
            config('services.google.client_id')
        ));

        if (! in_array($claims['iss'] ?? '', self::GOOGLE_ISSUERS, true)) {
            $this->reject('id_token', 'The Google token issuer is invalid.');
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => isset($claims['email']) ? (string) $claims['email'] : null,
            'name' => isset($claims['name']) ? (string) $claims['name'] : null,
        ];
    }

    /**
     * @return array{sub: string, email: string|null, aud: string}
     */
    public function verifyApple(string $identityToken): array
    {
        // Every bundle id we ship is a legitimate audience — a .dev build's
        // token carries `aud: com.get.everly.dev`, not the production id.
        $accepted = $this->audiences(config('services.apple.client_ids'));
        $claims = $this->decode($identityToken, self::APPLE_KEYS_URL, 'apple', $accepted);

        if (($claims['iss'] ?? '') !== self::APPLE_ISSUER) {
            $this->reject('identity_token', 'The Apple token issuer is invalid.');
        }

        $aud = array_map('strval', is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']]);

        return [
            'sub' => (string) $claims['sub'],
            // Apple only sends the email on the very first authorization.
            'email' => isset($claims['email']) ? (string) $claims['email'] : null,
            // Which of our bundle ids this token was minted for. Apple's token
            // and revoke endpoints demand the *same* client_id the credential
            // was issued to, so a .dev sign-in cannot be exchanged as production.
            'aud' => (string) current(array_intersect($accepted, $aud)),
        ];
    }

    /**
     * Normalises whatever config hands back — a single id, a list, or nothing —
     * into the accepted audiences for a provider. Blank entries are dropped so a
     * misconfigured `''` can never match an absent `aud`.
     *
     * @return list<string>
     */
    private function audiences(mixed $configured): array
    {
        $values = is_array($configured) ? $configured : [$configured];

        return array_values(array_filter(
            array_map(fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $values),
            fn (string $id): bool => $id !== '',
        ));
    }

    /**
     * @param  list<string>  $clientIds  every audience accepted for this provider
     * @return array<string, mixed>
     */
    private function decode(string $token, string $keysUrl, string $provider, array $clientIds): array
    {
        $field = $provider === 'apple' ? 'identity_token' : 'id_token';

        if ($clientIds === []) {
            $this->reject($field, ucfirst($provider).' sign-in is not configured.');
        }

        try {
            $keys = JWK::parseKeySet($this->signingKeys($keysUrl, $provider));

            // Decode verifies the signature and the exp/nbf claims for us.
            $claims = (array) JWT::decode($token, $keys);
        } catch (Throwable) {
            $this->reject($field, 'The '.$provider.' token could not be verified.');
        }

        // Audience must be one of *our* apps, or a token minted for some other
        // client would be accepted here.
        $aud = $claims['aud'] ?? null;
        $audiences = array_map('strval', is_array($aud) ? $aud : [$aud]);

        if (array_intersect($clientIds, $audiences) === []) {
            $this->reject($field, 'The '.$provider.' token was issued for another application.');
        }

        if (! isset($claims['sub'])) {
            $this->reject($field, 'The '.$provider.' token is missing a subject.');
        }

        return $claims;
    }

    /**
     * Provider JWKS rotate, but not often — cache briefly so a burst of logins
     * doesn't hammer the provider, while a rotation still heals on its own.
     *
     * @return array<string, mixed>
     */
    private function signingKeys(string $url, string $provider): array
    {
        return Cache::remember("social:jwks:{$provider}", now()->addHour(), function () use ($url): array {
            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Unable to fetch signing keys.');
            }

            return $response->json();
        });
    }

    /**
     * @return never
     *
     * @throws ValidationException
     */
    private function reject(string $field, string $message)
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
