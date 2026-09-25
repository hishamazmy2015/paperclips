<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\SiteResponses;
use App\Models\Tenant;
use App\Tenancy\Resolution;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec §11 — first middleware of a tenant site request:
 *  1. platform hosts never reach here as tenants (their own routes matched first) → 404;
 *  2. redirect rules → 301 with path and query preserved;
 *  3. host cache (Redis, DB fallback) → tenant entry, no query when warm;
 *  4. alias host → 301 to the primary host;
 *  5. suspended → 503 paused page; draft → owner/preview only, else 404; deleted → 404;
 *  6. bind the TenantContext for the rest of the request.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
        private readonly SiteResponses $responses,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolution = $this->resolver->resolve($request->getHost());

        switch ($resolution->kind) {
            case Resolution::PLATFORM:
                return $this->responses->unknown();

            case Resolution::REDIRECT:
                return redirect()->away('https://'.$resolution->redirectHost.$request->getRequestUri(), $resolution->redirectStatus);

            case Resolution::UNKNOWN:
                return $this->responses->unknown();
        }

        /** @var array{tenant_id: int, status: string, primary_host: string, tenant: array<string, mixed>} $entry */
        $entry = $resolution->entry;
        $tenant = Tenant::hydrate([$entry['tenant']])->first();
        if ($tenant === null) {
            return $this->responses->unknown();
        }
        $tenant->useResolvedPrimaryHost($entry['primary_host']);

        switch ($entry['status']) {
            case Tenant::STATUS_DELETED:
                return $this->responses->unknown();

            case Tenant::STATUS_SUSPENDED:
                return $this->context->run($tenant, fn () => $this->responses->paused());

            case Tenant::STATUS_DRAFT:
                if (! $this->mayPreview($request, $tenant)) {
                    return $this->responses->unknown();
                }
                $request->attributes->set('site_preview', true);
                break;

            case Tenant::STATUS_LIVE:
                if ((string) $request->query('preview', '') !== '' && $this->mayPreview($request, $tenant)) {
                    $request->attributes->set('site_preview', true);
                }
                break;
        }

        $this->context->set($tenant);
        $request->attributes->set('tenant', $tenant);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    /**
     * Draft sites are visible to their owner only (spec §11 step 5): a signed preview token
     * in the URL (remembered in the session) or, once auth exists, the owning user.
     */
    private function mayPreview(Request $request, Tenant $tenant): bool
    {
        $key = 'site_preview_'.$tenant->id;
        $token = (string) $request->query('preview', '');

        if ($token !== '' && hash_equals($tenant->previewToken(), $token)) {
            if ($request->hasSession()) {
                $request->session()->put($key, true);
            }

            return true;
        }

        if ($request->hasSession() && $request->session()->get($key) === true) {
            return true;
        }

        $user = $request->user();

        return $user !== null && (int) $user->getAttribute('account_id') === (int) $tenant->account_id;
    }
}
