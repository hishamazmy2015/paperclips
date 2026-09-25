<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Hosts;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security and SEO headers for tenant pages (spec §16, §17). Framing is denied, except for a
 * preview (token or owner session), which allows the app host as the only ancestor.
 */
final class SiteHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->get('site_preview') === true) {
            // the onboarding/dashboard preview embeds the draft in an iframe on the app host
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' ".Hosts::browserOrigin(Hosts::app()));
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
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
