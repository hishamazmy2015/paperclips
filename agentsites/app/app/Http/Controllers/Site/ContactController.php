<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Themes\SiteRenderer;
use Illuminate\Http\Response;

/** Contact page: WhatsApp-first (spec §2). The lead form (Turnstile, rate limits) is Phase 4. */
final class ContactController extends Controller
{
    public function __invoke(SiteRenderer $renderer): Response
    {
        return $renderer->render('contact');
    }
}
