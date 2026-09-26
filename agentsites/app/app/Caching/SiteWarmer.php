<?php

declare(strict_types=1);

namespace App\Caching;

use App\Models\Tenant;
use App\Platform\Hosts;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renders a tenant's main pages through the real HTTP kernel so the page cache is warm before
 * the first visitor (spec §12.3, §16). Console only (queue workers, CLI): a sync queue inside
 * an HTTP request skips it and lets the next visitor warm the page instead.
 */
final class SiteWarmer
{
    public function __construct(private readonly Kernel $kernel) {}

    /** @return list<string> paths without the locale prefix */
    public static function pages(Tenant $tenant): array
    {
        $pages = ['', 'listings', 'about', 'contact'];
        foreach ((array) data_get($tenant->mergedConfig(), 'content.service_areas', []) as $area) {
            $pages[] = 'areas/'.Str::slug((string) $area);
        }

        return $pages;
    }

    /** @return list<string> full paths, every enabled locale */
    public static function paths(Tenant $tenant): array
    {
        /** @var list<string> $locales */
        $locales = (array) data_get($tenant->mergedConfig(), 'locale.enabled', ['ar', 'en']);
        $paths = [];
        foreach ($locales as $locale) {
            foreach (self::pages($tenant) as $page) {
                $paths[] = '/'.$locale.($page === '' ? '' : '/'.$page);
            }
        }

        return $paths;
    }

    public static function available(): bool
    {
        return app()->runningInConsole();
    }

    /** Returns the number of pages that rendered with 200. */
    public function warm(Tenant $tenant): int
    {
        if (! self::available() || ! $tenant->isLive()) {
            return 0;
        }
        $host = $tenant->primaryHost();
        $previous = app()->bound('request') ? app('request') : null;
        $warmed = 0;

        foreach (self::paths($tenant) as $path) {
            // the public origin (app.url scheme + port), so the warmed page equals what a visitor gets
            $request = Request::create(Hosts::browserUrl($host, $path), 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
            try {
                $response = $this->kernel->handle($request);
                if ($response->getStatusCode() === 200) {
                    $warmed++;
                }
                $this->kernel->terminate($request, $response);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($previous !== null) {
            app()->instance('request', $previous);
            Facade::clearResolvedInstance('request');
        }

        return $warmed;
    }
}
