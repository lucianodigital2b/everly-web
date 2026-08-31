<?php

namespace App\Console\Commands;

use App\Services\AppleTokenClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reports the Sign in with Apple configuration.
 *
 * Deliberately offline. There is no way to ask Apple whether these credentials
 * are right: both /auth/token and /auth/revoke validate the code or token
 * *before* the client credentials, so they answer `invalid_grant` to anything —
 * including a fabricated team id, key id and bundle id. Only a real
 * authorization_code exercises the client secret, which means the first true
 * test is a live sign-in on a device.
 *
 * What can be checked here is everything short of that: the values are present,
 * the .p8 loads, ES256 signing works, and the key id matches the file.
 */
class CheckAppleSignInCommand extends Command
{
    protected $signature = 'apple:check';

    protected $description = 'Report the Sign in with Apple configuration';

    public function handle(AppleTokenClient $tokens): int
    {
        $clientIds = (array) config('services.apple.client_ids');

        $this->line('');
        $this->line('<options=bold>Sign in with Apple</>');
        $this->line('');

        // Sign-in and revocation fail independently, so report them that way.
        $this->line('  <options=bold>Sign-in</> — identity token verification');
        $this->keyValue('bundle ids (aud)', $clientIds === [] ? null : implode(', ', $clientIds));

        if ($clientIds === []) {
            $this->line('');
            $this->error('  APPLE_CLIENT_ID is empty — every sign-in will be rejected.');

            return self::FAILURE;
        }

        $this->line('  <fg=green>✓</> Needs nothing further: tokens verify against Apple\'s public keys.');
        $this->line('');

        $this->line('  <options=bold>Revocation</> — account deletion, guideline 5.1.1(v)');
        $this->keyValue('team id', config('services.apple.team_id'));
        $this->keyValue('key id', config('services.apple.key_id'));
        $this->keyValue('private key', $this->privateKeySource());

        if (! $tokens->isConfigured()) {
            $this->line('');
            $this->warn('  ✗ Incomplete — deletion will not revoke the Apple grant.');
            $this->line('    Sign-in still works. Set APPLE_TEAM_ID, APPLE_KEY_ID and the .p8 to fix.');

            return self::FAILURE;
        }

        try {
            $tokens->assertKeyUsable((string) $clientIds[0]);
        } catch (Throwable $e) {
            $this->line('');
            $this->error('  ✗ The private key could not be used to sign: '.$e->getMessage());
            $this->line('    Expected an ES256 (P-256) key — the .p8 downloaded from the portal.');

            return self::FAILURE;
        }

        $this->line('  <fg=green>✓</> Key loads and signs.');

        foreach ($this->keyIdMismatch() as $warning) {
            $this->line('  <fg=yellow>!</> '.$warning);
        }

        $this->line('');
        $this->line('  <fg=yellow>Not verified:</> whether this key is authorised for these bundle ids.');
        $this->line('  Apple only reveals that on a real authorization_code, so confirm by');
        $this->line('  signing in on a device and deleting the account — a failed revoke is');
        $this->line('  logged as <options=bold>Apple token revocation failed</> with an invalid_client error.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * The file name carries the key id, so a mismatch is a copy-paste slip worth
     * naming — Apple would otherwise just reject the signature at deletion time.
     *
     * @return list<string>
     */
    private function keyIdMismatch(): array
    {
        $path = (string) config('services.apple.private_key_path');
        $keyId = (string) config('services.apple.key_id');

        if ($path === '' || $keyId === '' || ! preg_match('/AuthKey_(\w+)\.p8$/', $path, $m)) {
            return [];
        }

        return $m[1] === $keyId
            ? []
            : ["APPLE_KEY_ID is {$keyId} but the file is AuthKey_{$m[1]}.p8 — one of them is wrong."];
    }

    private function privateKeySource(): ?string
    {
        if ((string) config('services.apple.private_key') !== '') {
            return 'inline (APPLE_PRIVATE_KEY)';
        }

        $path = (string) config('services.apple.private_key_path');

        if ($path === '') {
            return null;
        }

        $resolved = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);

        return is_readable($resolved) ? $path : "{$path} <fg=red>(not readable)</>";
    }

    private function keyValue(string $label, ?string $value): void
    {
        $shown = ($value === null || $value === '') ? '<fg=red>not set</>' : $value;

        $this->line(sprintf('    %-18s %s', $label, $shown));
    }
}
