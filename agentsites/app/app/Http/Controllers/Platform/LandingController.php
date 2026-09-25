<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** S0 landing (spec §13). The full onboarding entry point arrives in Phase 2. */
final class LandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $locale = $request->query('lang') === 'ar' ? 'ar' : ($request->getPreferredLanguage(['en', 'ar']) ?? 'en');
        app()->setLocale($locale);

        $published = Cache::remember('landing.published_count', 60, fn (): int => Tenant::query()->live()->count());

        return view('platform.landing', ['locale' => $locale, 'published' => $published]);
    }
}
