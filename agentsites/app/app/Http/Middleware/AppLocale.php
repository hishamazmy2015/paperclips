<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI language on the app host (spec §13: ar/en toggle top-right, RTL when ar):
 * ?lang= (remembered in the session) → session → the account's locale → Accept-Language.
 */
final class AppLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $locales */
        $locales = config('platform.locales');
        $locale = (string) $request->query('lang', '');

        if (in_array($locale, $locales, true)) {
            $request->session()->put('locale', $locale);
        } else {
            $locale = (string) $request->session()->get('locale', '');
        }
        if (! in_array($locale, $locales, true)) {
            $locale = (string) ($request->user()?->account->locale ?? '');
        }
        if (! in_array($locale, $locales, true)) {
            $locale = $request->getPreferredLanguage($locales) ?? (string) config('platform.default_locale');
        }

        app()->setLocale($locale);
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
