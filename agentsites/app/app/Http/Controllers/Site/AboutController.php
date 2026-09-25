<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;

final class AboutController extends Controller
{
    public function __invoke(SiteRenderer $renderer): Response
    {
        return $renderer->render('about', [
            'testimonials' => $renderer->testimonials()->limit(6)->get(),
        ]);
    }
}
