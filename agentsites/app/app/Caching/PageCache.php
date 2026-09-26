<?php

declare(strict_types=1);

namespace App\Caching;

use App\Models\Tenant;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Full-page cache for tenant sites (spec §16): keyed by host + path + locale (the locale is the
 * first path segment) + query, TTL 1 h, "tagged" by tenant through a per-tenant version number
 * folded into every key — so purging is one write and works on Redis, array and file stores
 * alike. Purged on config save, publish, lifecycle changes and listing/testimonial/domain writes.
 *
 * @phpstan-type CachedPage array{status: int, headers: array<string, string>, body: string, etag: string, stored_at: int}
 */
final class PageCache
{
    public const TTL_SECONDS = 3600;

    public function __construct(private readonly CacheRepository $cache) {}

    public function version(int $tenantId): int
    {
        return (int) $this->cache->get('page:ver:'.$tenantId, 1) + (int) $this->cache->get('page:ver:all', 0);
    }

    public function key(int $tenantId, string $host, string $path, string $query = ''): string
    {
        return 'page:'.$tenantId.':v'.$this->version($tenantId).':'.sha1(strtolower($host).'|'.$path.'|'.$query);
    }

    /** @return CachedPage|null */
    public function get(int $tenantId, string $host, string $path, string $query = ''): ?array
    {
        $entry = $this->cache->get($this->key($tenantId, $host, $path, $query));

        /** @var CachedPage|null $entry */
        return is_array($entry) ? $entry : null;
    }

    /** @param  array<string, string>  $headers */
    public function put(int $tenantId, string $host, string $path, string $query, int $status, array $headers, string $body): string
    {
        $etag = '"'.substr(sha1($body), 0, 32).'"';
        $this->cache->put($this->key($tenantId, $host, $path, $query), [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
            'etag' => $etag,
            'stored_at' => time(),
        ], self::TTL_SECONDS);

        return $etag;
    }

    /** Every cached page of one tenant is stale from now on (one write, no scan). */
    public function purgeTenant(Tenant|int $tenant): void
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $this->cache->forever('page:ver:'.$id, (int) $this->cache->get('page:ver:'.$id, 1) + 1);
    }

    /** Every cached page of every tenant (a deploy that changes templates, `site:regenerate --all`). */
    public function purgeAll(): void
    {
        $this->cache->forever('page:ver:all', (int) $this->cache->get('page:ver:all', 0) + 1);
    }
}
