<?php

declare(strict_types=1);

namespace App\Themes;

use App\Models\Listing;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * Renders a theme page for the bound tenant with the shared SiteContext view model. Also the
 * one place that decides which listings a site shows: demo listings only until real ones
 * exist (spec §14).
 */
final class SiteRenderer
{
    private ?SiteContext $site = null;

    public function __construct(private readonly ThemeRegistry $themes) {}

    public function tenant(): Tenant
    {
        return TenantContext::require();
    }

    public function site(): SiteContext
    {
        return $this->site ??= SiteContext::for($this->tenant(), app()->getLocale());
    }

    /** @param  array<string, mixed>  $data */
    public function render(string $page, array $data = [], int $status = 200): Response
    {
        $view = $this->themes->view($this->tenant()->theme_key, $page);
        $html = View::make($view, ['site' => $this->site()] + $data)->render();

        return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Visible listings: real available listings, or the demo set while none exist and the
     * site allows it.
     *
     * @return Builder<Listing>
     */
    public function listings(): Builder
    {
        $query = Listing::query()->available();
        $hasReal = Listing::query()->available()->real()->exists();
        $showDemo = (bool) ($this->site()->config['listings']['show_demo_until_real'] ?? true);

        if ($hasReal || ! $showDemo) {
            $query->real();
        }

        return $query;
    }

    /** @return Builder<Testimonial> */
    public function testimonials(): Builder
    {
        return Testimonial::query()->orderByDesc('featured')->orderBy('sort_order')->orderBy('id');
    }
}
