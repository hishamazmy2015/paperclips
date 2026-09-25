<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Platform\Hosts;
use App\Tenancy\HostName;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * GET /internal/tls/allow?domain=x (spec §11): 200 only when the host is a verified domain of
 * a live or draft tenant (or a tenant's own subdomain), else 404. Reachable only through
 * Caddy's loopback listener, whose requests carry Host 127.0.0.1; every decision is logged.
 */
final class TlsAllowController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $host = HostName::normalize((string) $request->query('domain', ''));
        $via = HostName::normalize($request->getHost());

        if (! in_array($via, ['127.0.0.1', 'localhost', '::1'], true)) {
            return $this->decide($host, false, 'not-internal', $via);
        }
        if ($host === '' || ! HostName::isValid($host)) {
            return $this->decide($host, false, 'invalid-host');
        }

        $base = Hosts::base();
        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));
            $tenant = Tenant::query()->where('slug', $slug)->first();

            return $this->decide($host, $tenant !== null && $this->allowedStatus($tenant), 'subdomain');
        }

        $domain = Domain::unscopedByTenant()->where('host', $host)->where('verified', true)->first();
        if ($domain === null) {
            return $this->decide($host, false, 'unknown-or-unverified');
        }
        $tenant = Tenant::query()->find($domain->tenant_id);

        return $this->decide($host, $tenant !== null && $this->allowedStatus($tenant), 'custom', tenantId: $domain->tenant_id);
    }

    private function allowedStatus(Tenant $tenant): bool
    {
        return in_array($tenant->status, [Tenant::STATUS_LIVE, Tenant::STATUS_DRAFT], true);
    }

    private function decide(string $host, bool $allow, string $reason, ?string $via = null, ?int $tenantId = null): Response
    {
        Log::info('tls.allow', ['host' => $host, 'allow' => $allow, 'reason' => $reason, 'via' => $via, 'tenant_id' => $tenantId]);

        return new Response($allow ? 'ok' : 'denied', $allow ? 200 : 404, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
