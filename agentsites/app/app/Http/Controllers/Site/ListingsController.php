<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ListingsController extends Controller
{
    public function index(Request $request, SiteRenderer $renderer): Response
    {
        $query = $renderer->listings();

        $offering = (string) $request->query('offering', '');
        if (in_array($offering, ['sale', 'rent'], true)) {
            $query->where('offering', $offering);
        }
        $type = (string) $request->query('type', '');
        if ($type !== '' && strlen($type) <= 40) {
            $query->where('property_type', $type);
        }
        $beds = $request->query('beds');
        if (is_numeric($beds)) {
            $query->where('bedrooms', '>=', (int) $beds);
        }
        $community = (string) $request->query('area', '');
        if ($community !== '' && strlen($community) <= 120) {
            $query->where('community', $community);
        }

        return $renderer->render('listings', [
            'listings' => $query->orderByDesc('featured')->orderByDesc('id')->paginate(12)->withQueryString(),
            'filters' => ['offering' => $offering, 'type' => $type, 'beds' => is_numeric($beds) ? (int) $beds : null, 'area' => $community],
        ]);
    }

    public function show(string $locale, string $ref, SiteRenderer $renderer): Response
    {
        $listing = $renderer->listings()->where('ref', $ref)->first();
        if ($listing === null) {
            abort(404);
        }

        return $renderer->render('listing', [
            'listing' => $listing,
            'related' => $renderer->listings()->where('id', '!=', $listing->id)->where('community', $listing->community)->limit(3)->get(),
        ]);
    }
}
