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
     * A URL for the browser that made the current request: same scheme and port as that
     * request (http://…:8123 in dev, https://…:8444 before the cutover, https://… after), so
     * links, iframes and share buttons work in every environment. https:// outside HTTP.
     */
    public static function browserUrl(string $host, string $path = '/'): string
    {
        return self::browserOrigin($host).'/'.ltrim($path, '/');
    }

    public static function browserOrigin(string $host): string
    {
        if (! app()->bound('request') || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return self::publicOrigin($host);
        }
        $request = request();

        return self::origin($request->getScheme(), $host, (int) $request->getPort());
    }

    /**
     * The origin a browser reaches a host on when there is no request to copy it from (queued
     * jobs, the cache warmer): the scheme and port of `app.url` (https, no port, in production;
     * http://…:8123 in the E2E environment), so warmed pages carry the same absolute asset URLs
     * as pages rendered for a visitor.
     */
    public static function publicOrigin(string $host): string
    {
        $app = parse_url((string) config('app.url')) ?: [];
        $scheme = strtolower((string) ($app['scheme'] ?? 'https')) === 'http' ? 'http' : 'https';

        return self::origin($scheme, $host, (int) ($app['port'] ?? 0));
    }

    private static function origin(string $scheme, string $host, int $port): string
    {
        $default = $scheme === 'https' ? 443 : 80;

        return $scheme.'://'.$host.($port !== 0 && $port !== $default ? ':'.$port : '');
    }

    /**
     * Platform-owned hosts (apex, app., admin., api., cdn., staging.): the tenant
     * resolver skips these instead of looking them up as tenant domains.
     */
    public static function isPlatformHost(string $host): bool
    {
        $host = self::normalize($host);
        $base = self::base();

        return $host === $base || $host === 'www.'.$base || in_array($host, self::all(), true);
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
