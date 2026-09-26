<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Listings\ListingCsv;
use Illuminate\Http\Response;

/** The listings CSV template download (spec §14). */
final class ListingsTemplateController extends Controller
{
    public function __invoke(): Response
    {
        return new Response(ListingCsv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="listings-template.csv"',
        ]);
    }
}
