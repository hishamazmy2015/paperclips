<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * Outcome of resolving a request host (spec §11 steps 1–5).
 *
 * @phpstan-import-type HostEntry from HostCache
 */
final class Resolution
{
    public const PLATFORM = 'platform';

    public const REDIRECT = 'redirect';

    public const TENANT = 'tenant';

    public const UNKNOWN = 'unknown';

    /**
     * @param  self::*  $kind
     * @param  HostEntry|null  $entry
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?array $entry = null,
        public readonly ?string $redirectHost = null,
        public readonly int $redirectStatus = 301,
    ) {}

    public static function platform(): self
    {
        return new self(self::PLATFORM);
    }

    public static function redirect(string $toHost, int $status = 301): self
    {
        return new self(self::REDIRECT, null, $toHost, $status);
    }

    /** @param  HostEntry  $entry */
    public static function tenant(array $entry): self
    {
        return new self(self::TENANT, $entry);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isTenant(): bool
    {
        return $this->kind === self::TENANT;
    }
}
