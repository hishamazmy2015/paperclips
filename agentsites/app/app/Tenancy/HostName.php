<?php

declare(strict_types=1);

namespace App\Tenancy;

/** Host normalisation shared by the resolver, caches and domain writes. */
final class HostName
{
    /** Lowercase, trimmed, without port or trailing dot. */
    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = explode(':', $host, 2)[0];

        return rtrim($host, '.');
    }

    public static function isValid(string $host): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host);
    }
}
