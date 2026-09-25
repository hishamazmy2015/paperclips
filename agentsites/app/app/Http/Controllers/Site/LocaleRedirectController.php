<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * / → /{locale}/ (spec §16): the first Accept-Language entry the site has enabled wins,
 * otherwise the site's default locale.
 */
final class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $tenant = TenantContext::require();
        $config = $tenant->mergedConfig();
        /** @var list<string> $enabled */
        $enabled = array_values((array) ($config['locale']['enabled'] ?? ['ar', 'en']));
        $default = (string) ($config['locale']['default'] ?? 'en');

        $locale = in_array($default, $enabled, true) ? $default : ($enabled[0] ?? 'en');
        foreach ($request->getLanguages() as $language) {
            $short = strtolower(substr($language, 0, 2));
            if (in_array($short, $enabled, true)) {
                $locale = $short;
                break;
            }
        }

        $query = $request->getQueryString();

        return redirect('/'.$locale.($query !== null ? '?'.$query : ''), 302)->header('Vary', 'Accept-Language');
    }
}
