<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\RedirectRule;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Host-level redirects (spec §11 step 2): cache first, then the redirect_rules table.
 *
 * @phpstan-type RedirectEntry array{to_host: string, status_code: int}
 */
final class RedirectRules
{
    public const TTL_SECONDS = 600;

    public const NEGATIVE_TTL_SECONDS = 60;

    private const MISS = '__miss__';

    public function __construct(private readonly CacheRepository $cache) {}

    public static function key(string $host): string
    {
        return 'redirect:'.$host;
    }

    /** @return RedirectEntry|null */
    public function lookup(string $host): ?array
    {
        $host = HostName::normalize($host);
        $cached = $this->cache->get(self::key($host));

        if ($cached === self::MISS) {
            return null;
        }
        if (is_array($cached)) {
            /** @var RedirectEntry $cached */
            return $cached;
        }

        $rule = RedirectRule::query()->active()->where('from_host', $host)->first();
        $entry = $rule === null ? null : ['to_host' => $rule->to_host, 'status_code' => $rule->status_code];
        $this->cache->put(self::key($host), $entry ?? self::MISS, $entry === null ? self::NEGATIVE_TTL_SECONDS : self::TTL_SECONDS);

        return $entry;
    }

    public function add(string $fromHost, string $toHost, int $statusCode = 301, ?CarbonInterface $expiresAt = null): RedirectRule
    {
        $fromHost = HostName::normalize($fromHost);
        $rule = RedirectRule::query()->updateOrCreate(
            ['from_host' => $fromHost],
            ['to_host' => HostName::normalize($toHost), 'status_code' => $statusCode, 'expires_at' => $expiresAt],
        );
        $this->cache->forget(self::key($fromHost));

        return $rule;
    }

    public function remove(string $fromHost): void
    {
        $fromHost = HostName::normalize($fromHost);
        RedirectRule::query()->where('from_host', $fromHost)->delete();
        $this->cache->forget(self::key($fromHost));
    }

    /** Delete expired rules; returns how many were removed. */
    public function purgeExpired(): int
    {
        return RedirectRule::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
    }
}
