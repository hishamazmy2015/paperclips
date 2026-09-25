<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Per-area landing page (spec §16): /{locale}/areas/{area-slug}. */
final class AreaController extends Controller
{
    public function __invoke(string $locale, string $area, SiteRenderer $renderer): Response
    {
        $match = null;
        foreach ($renderer->site()->areas as $candidate) {
            if (Str::slug($candidate) === $area) {
                $match = $candidate;
                break;
            }
        }
        if ($match === null) {
            abort(404);
        }

        return $renderer->render('area', [
            'area' => $match,
            'listings' => $renderer->listings()->where('community', $match)->orderByDesc('featured')->orderByDesc('id')->limit(12)->get(),
        ]);
    }
}
