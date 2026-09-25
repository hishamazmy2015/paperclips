<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile on sign-up forms (spec §6, §17). Off until both keys are configured;
 * when on, a missing or unverifiable token fails closed.
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return $this->siteKey() !== '' && $this->secret() !== '';
    }

    public function siteKey(): string
    {
        return (string) config('providers.turnstile.site_key', '');
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        if ($token === null || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $this->secret(),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return $response->successful() && (bool) $response->json('success', false);
        } catch (Throwable $e) {
            Log::warning('turnstile.unavailable', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function secret(): string
    {
        return (string) config('providers.turnstile.secret', '');
    }
}
