<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;

/** Per-tenant robots.txt (spec §16): crawlable when live and indexable, otherwise Disallow all. */
final class RobotsController extends Controller
{
    public function __invoke(SiteRenderer $renderer): Response
    {
        $tenant = $renderer->tenant();
        $noindex = $tenant->isDraft() || (bool) data_get($tenant->mergedConfig(), 'seo.noindex', false);

        $lines = ['User-agent: *'];
        if ($noindex) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /media/';
            $lines[] = 'Sitemap: https://'.$tenant->primaryHost().'/sitemap.xml';
        }

        return new Response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
