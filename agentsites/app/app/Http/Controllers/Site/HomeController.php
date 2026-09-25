<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;

final class HomeController extends Controller
{
    public function __invoke(SiteRenderer $renderer): Response
    {
        return $renderer->render('home', [
            'featured' => $renderer->listings()->featured()->orderByDesc('featured')->orderByDesc('id')->limit(6)->get(),
            'testimonials' => $renderer->testimonials()->limit(6)->get(),
        ]);
    }
}
