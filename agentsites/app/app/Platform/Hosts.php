<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Host derivation from the single configured base domain (spec §4).
 * Nothing else in the codebase may build a platform host by hand.
 */
final class Hosts
{
    public static function base(): string
    {
        return (string) config('platform.base_domain');
    }

    public static function app(): string
    {
        return self::named('app');
    }

    public static function admin(): string
    {
        return self::named('admin');
    }

    public static function api(): string
    {
        return self::named('api');
    }

    public static function cdn(): string
    {
        return self::named('cdn');
    }

    public static function staging(): string
    {
        return self::named('staging');
    }

    public static function tenant(string $slug): string
    {
        return strtolower(trim($slug)).'.'.self::base();
    }

    public static function url(string $host, string $path = '/'): string
    {
        return 'https://'.$host.'/'.ltrim($path, '/');
    }

    /**
     * Platform-owned hosts (apex, app., admin., api., cdn., staging.): the tenant
     * resolver skips these instead of looking them up as tenant domains.
     */
    public static function isPlatformHost(string $host): bool
    {
        $host = self::normalize($host);

        return $host === self::base() || in_array($host, self::all(), true);
    }

    public static function isLegacyHost(string $host): bool
    {
        /** @var list<string> $legacy */
        $legacy = config('platform.legacy_hosts', []);

        return in_array(self::normalize($host), $legacy, true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        /** @var array<string, string> $hosts */
        $hosts = config('platform.hosts', []);

        return array_values($hosts);
    }

    private static function named(string $key): string
    {
        return (string) config('platform.hosts.'.$key);
    }

    /** Lowercase, without port. */
    public static function normalize(string $host): string
    {
        return strtolower(explode(':', trim($host), 2)[0]);
    }
}
