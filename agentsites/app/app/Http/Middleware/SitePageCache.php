<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Caching\PageCache;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves live tenant pages from the page cache (spec §16): anonymous GET/HEAD on a live site,
 * never a preview. Hits carry ETag/Cache-Control and answer If-None-Match with 304; misses are
 * stored (200, text/html|xml|plain, cookies stripped) for an hour or until the tenant's pages
 * are purged.
 */
final class SitePageCache
{
    /** @var list<string> */
    private const CACHEABLE_TYPES = ['text/html', 'application/xml', 'text/xml', 'text/plain'];

    /** @var list<string> */
    private const KEPT_HEADERS = ['content-type', 'content-language', 'vary', 'x-robots-tag'];

    public function __construct(private readonly PageCache $pages) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $tenant = TenantContext::current();
        if ($tenant === null || ! $this->cacheable($request, $tenant)) {
            return $next($request);
        }

        $host = $request->getHost();
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $query = self::normalizedQuery($request);

        $cached = $this->pages->get($tenant->id, $host, $path, $query);
        if ($cached !== null) {
            if ($request->headers->get('If-None-Match') === $cached['etag']) {
                return (new Response('', 304))->withHeaders(['ETag' => $cached['etag'], 'Cache-Control' => 'public, max-age=60', 'X-Cache' => 'HIT']);
            }

            return (new Response($cached['body'], $cached['status'], $cached['headers']))->withHeaders([
                'ETag' => $cached['etag'],
                'Cache-Control' => 'public, max-age=60',
                'Age' => (string) max(0, time() - $cached['stored_at']),
                'X-Cache' => 'HIT',
            ]);
        }

        $response = $next($request);
        if (! $this->storable($response)) {
            return $response;
        }

        $headers = [];
        foreach (self::KEPT_HEADERS as $name) {
            $value = $response->headers->get($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }
        $etag = $this->pages->put($tenant->id, $host, $path, $query, $response->getStatusCode(), $headers, (string) $response->getContent());
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=60');
        $response->headers->set('X-Cache', 'MISS');

        return $response;
    }

    private function cacheable(Request $request, Tenant $tenant): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && $tenant->isLive()
            && $request->attributes->get('site_preview') !== true
            && $request->query('preview') === null
            && $request->user() === null;
    }

    private function storable(SymfonyResponse $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        foreach (self::CACHEABLE_TYPES as $cacheable) {
            if (str_starts_with($type, $cacheable)) {
                return true;
            }
        }

        return false;
    }

    /** Sorted query string so ?a=1&b=2 and ?b=2&a=1 share one entry. */
    public static function normalizedQuery(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return http_build_query($query);
    }
}
