<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Per-tenant sitemap.xml (spec §16): home, listings, real listings, areas, about and contact in
 * every enabled locale, each with hreflang alternates. Demo listings are not listed; a draft,
 * paused or noindex site gets an empty sitemap.
 */
final class SitemapController extends Controller
{
    public function __invoke(SiteRenderer $renderer): Response
    {
        $tenant = $renderer->tenant();
        $config = $tenant->mergedConfig();
        /** @var list<string> $locales */
        $locales = array_values((array) data_get($config, 'locale.enabled', ['ar', 'en']));
        $host = 'https://'.$tenant->primaryHost();
        $noindex = $tenant->isDraft() || (bool) data_get($config, 'seo.noindex', false);

        $pages = [];
        if (! $noindex) {
            $pages = [['path' => '', 'priority' => '1.0', 'freq' => 'daily'], ['path' => 'listings', 'priority' => '0.9', 'freq' => 'daily'], ['path' => 'about', 'priority' => '0.6', 'freq' => 'monthly'], ['path' => 'contact', 'priority' => '0.6', 'freq' => 'monthly']];
            foreach ((array) data_get($config, 'content.service_areas', []) as $area) {
                $pages[] = ['path' => 'areas/'.Str::slug((string) $area), 'priority' => '0.7', 'freq' => 'weekly'];
            }
            $refs = Listing::query()->available()->real()->orderByDesc('id')->limit(5000)->pluck('updated_at', 'ref');
            foreach ($refs as $ref => $updated) {
                $pages[] = ['path' => 'listings/'.$ref, 'priority' => '0.8', 'freq' => 'weekly', 'lastmod' => $updated];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $xml .= '  <url>'."\n";
                $xml .= '    <loc>'.self::e($host.'/'.$locale.($page['path'] === '' ? '' : '/'.$page['path'])).'</loc>'."\n";
                foreach ($locales as $alternate) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="'.$alternate.'" href="'.self::e($host.'/'.$alternate.($page['path'] === '' ? '' : '/'.$page['path'])).'"/>'."\n";
                }
                if (isset($page['lastmod'])) {
                    $xml .= '    <lastmod>'.$page['lastmod']->toDateString().'</lastmod>'."\n";
                }
                $xml .= '    <changefreq>'.$page['freq'].'</changefreq>'."\n";
                $xml .= '    <priority>'.$page['priority'].'</priority>'."\n";
                $xml .= '  </url>'."\n";
            }
        }
        $xml .= '</urlset>'."\n";

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
