<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\OtpRejected;
use App\Auth\Exceptions\OtpThrottled;
use App\Models\OtpCode;
use Illuminate\Cache\RateLimiter;

/**
 * One-time codes for email and WhatsApp sign-in (spec §13 S1, §17): 6 digits, 10 minutes,
 * 5 attempts per code, send/verify limits per identifier and per IP. Codes are stored as
 * keyed hashes; the plain code exists only in the message that carries it.
 */
final class OtpService
{
    public const LENGTH = 6;

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** Sends per identifier per 10 minutes. */
    public const SENDS_PER_IDENTIFIER = 3;

    /** Sends per IP per 10 minutes. */
    public const SENDS_PER_IP = 10;

    /** Verification attempts per IP per 10 minutes. */
    public const VERIFIES_PER_IP = 30;

    private const WINDOW_SECONDS = 600;

    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Issue a fresh code; any earlier live code for the identifier stops working.
     *
     * @return array{otp: OtpCode, code: string}
     *
     * @throws OtpThrottled
     */
    public function issue(string $identifier, string $channel, ?string $ip): array
    {
        $identifier = self::normalizeIdentifier($identifier);
        $this->hit('otp:send:id:'.$identifier, self::SENDS_PER_IDENTIFIER);
        if ($ip !== null) {
            $this->hit('otp:send:ip:'.$ip, self::SENDS_PER_IP);
        }

        OtpCode::query()->where('identifier', $identifier)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);
        $otp = OtpCode::query()->create([
            'identifier' => $identifier,
            'channel' => $channel,
            'code_hash' => $this->hash($identifier, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'attempts' => 0,
            'ip' => $ip,
        ]);

        return ['otp' => $otp, 'code' => $code];
    }

    /**
     * Check a typed code against the live one for the identifier and consume it.
     *
     * @throws OtpRejected
     * @throws OtpThrottled
     */
    public function verify(string $identifier, string $code, ?string $ip): OtpCode
    {
        $identifier = self::normalizeIdentifier($identifier);
        if ($ip !== null) {
            $this->hit('otp:verify:ip:'.$ip, self::VERIFIES_PER_IP);
        }
        $code = self::digits($code);

        $otp = $this->live($identifier);
        if ($otp === null) {
            throw new OtpRejected('expired');
        }
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw new OtpRejected('attempts');
        }
        if (strlen($code) !== self::LENGTH || ! hash_equals($otp->code_hash, $this->hash($identifier, $code))) {
            $attempts = $otp->attempts + 1;
            $otp->attempts = $attempts;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $otp->consumed_at = now();
                $otp->save();

                throw new OtpRejected('attempts');
            }
            $otp->save();

            throw new OtpRejected('invalid', self::MAX_ATTEMPTS - $attempts);
        }

        $otp->consumed_at = now();
        $otp->save();

        return $otp;
    }

    /** Magic link: the signed URL carries the id; consuming it is the proof of possession. */
    public function consume(int $id): ?OtpCode
    {
        $otp = OtpCode::query()->whereKey($id)->whereNull('consumed_at')->where('expires_at', '>', now())->first();
        if ($otp === null) {
            return null;
        }
        $otp->consumed_at = now();
        $otp->save();

        return $otp;
    }

    public function live(string $identifier): ?OtpCode
    {
        return OtpCode::query()
            ->where('identifier', self::normalizeIdentifier($identifier))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /** Arabic-Indic digits are what an Arabic keyboard types (spec §16). */
    public static function digits(string $code): string
    {
        $code = strtr($code, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return preg_replace('/\D+/', '', $code) ?? '';
    }

    private function hit(string $key, int $max): void
    {
        if ($this->limiter->tooManyAttempts($key, $max)) {
            throw new OtpThrottled($this->limiter->availableIn($key));
        }
        $this->limiter->hit($key, self::WINDOW_SECONDS);
    }

    private function hash(string $identifier, string $code): string
    {
        return hash_hmac('sha256', $identifier.'|'.$code, (string) config('app.key'));
    }
}
