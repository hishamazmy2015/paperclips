<?php

declare(strict_types=1);

namespace App\Http;

use App\Tenancy\TenantContext;
use App\Themes\SiteContext;
use App\Themes\ThemeRegistry;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/** Themed 404 / paused pages and the generic unknown-host response (spec §11, §16). */
final class SiteResponses
{
    public function __construct(private readonly ThemeRegistry $themes) {}

    /** Unknown host, deleted tenant or a draft without preview rights: plain 404, noindex. */
    public function unknown(): Response
    {
        return new Response(View::make('platform.unknown-host')->render(), 404, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function notFound(): Response
    {
        $tenant = TenantContext::current();
        if ($tenant === null) {
            return $this->unknown();
        }

        $site = SiteContext::for($tenant, app()->getLocale());

        return new Response(View::make($this->themes->view($tenant->theme_key, '404'), ['site' => $site])->render(), 404, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** Suspended site (spec §11 step 5): 503, noindex, Retry-After. */
    public function paused(): Response
    {
        $tenant = TenantContext::require();
        $site = SiteContext::for($tenant, $tenant->defaultLocale());

        return new Response(View::make($this->themes->view($tenant->theme_key, 'paused'), ['site' => $site])->render(), 503, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => '3600',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'no-store',
        ]);
    }
}
