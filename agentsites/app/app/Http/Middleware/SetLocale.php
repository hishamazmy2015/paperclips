<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** /{locale}/... on tenant sites (spec §16): app locale, URL default, Content-Language. */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');
        /** @var list<string> $locales */
        $locales = config('platform.locales');
        if (! in_array($locale, $locales, true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
