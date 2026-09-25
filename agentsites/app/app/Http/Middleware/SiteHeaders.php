<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security and SEO headers for tenant pages (spec §16, §17). Framing is denied; the dashboard
 * preview (Phase 4) allows the app host as the only ancestor.
 */
final class SiteHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Vary', 'Accept-Language', false);

        $tenant = TenantContext::current();
        if ($tenant !== null && ($tenant->isDraft() || (bool) data_get($tenant->mergedConfig(), 'seo.noindex', false))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
