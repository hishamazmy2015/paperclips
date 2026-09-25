<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Platform\Hosts;
use App\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** S0 landing (spec §13): headline, CTA to app.{base}/start, three theme previews, live counter. */
final class LandingController extends Controller
{
    public function __invoke(Request $request, ThemeRegistry $themes): View
    {
        $locale = $request->query('lang') === 'ar' ? 'ar' : ($request->getPreferredLanguage(['en', 'ar']) ?? 'en');
        app()->setLocale($locale);

        $published = Cache::remember('landing.published_count', 60, fn (): int => Tenant::query()->live()->count());

        $installed = $themes->installed();
        $cards = [];
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('themes.themes', []);
        foreach ($configured as $key => $theme) {
            $cards[] = ['key' => $key, 'name' => (string) ($theme['name'] ?? $key), 'installed' => in_array($key, $installed, true), 'preview' => '/themes/'.$key.'-preview.svg'];
        }

        return view('platform.landing', [
            'locale' => $locale,
            'published' => $published,
            'themes' => $cards,
            'startUrl' => Hosts::browserUrl(Hosts::app(), '/start'),
        ]);
    }
}
