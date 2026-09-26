<?php

declare(strict_types=1);

use App\Listings\DemoSeeder;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Provisioning\TenantLifecycle;
use App\Themes\ThemeRegistry;

// Spec §10: three distinct themes, every page in both locales, RTL, sections from the config,
// dark mode only where the theme allows it.

function themedTenant(string $theme, string $slug): Tenant
{
    $tenant = test()->makeTenant($slug, Tenant::STATUS_LIVE, [
        'identity' => ['display_name' => 'Ahmed Al Falasi', 'agency_name' => 'Falasi Properties', 'tagline' => ['en' => 'Your partner in Dubai', 'ar' => 'شريكك في دبي']],
        'content' => ['service_areas' => ['Downtown', 'Marina']],
        'branding' => ['dark_mode' => 'on'],
    ]);
    $tenant->update(['theme_key' => $theme]);
    app(DemoSeeder::class)->seed($tenant);
    Testimonial::factory()->create(['tenant_id' => $tenant->id, 'author_name' => 'Fatima K.', 'text_en' => 'Found our villa in a week.', 'text_ar' => 'وجدنا فيلتنا خلال أسبوع.']);

    return $tenant->fresh();
}

it('installs all three themes', function (): void {
    expect(app(ThemeRegistry::class)->installed())->toBe(['atlas', 'marina', 'palm']);
});

foreach (['marina', 'palm'] as $theme) {
    it("renders every {$theme} page in both locales with the tenant's content", function () use ($theme): void {
        $tenant = themedTenant($theme, $theme.'-agent');
        $host = 'http://'.$theme.'-agent.example.test';

        $home = $this->get($host.'/en')->assertOk()
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertSee('Ahmed Al Falasi')
            ->assertSee('Your partner in Dubai')
            ->assertSee('Found our villa in a week.')
            ->assertSee('Downtown')
            ->assertSee('wa.me/971', false)
            ->assertSee('--c-primary:#8f6229', false)
            ->assertSee('data-dark="off"', false) // neither theme renders dark (manifest dark_capable: false)
            ->assertSee('"@type":["RealEstateAgent","LocalBusiness"]', false);

        $this->get($host.'/ar')->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('شريكك في دبي')->assertSee('وجدنا فيلتنا خلال أسبوع.')->assertHeader('Content-Language', 'ar');
        $this->get($host.'/en/listings')->assertOk()->assertSee('DEMO-1', false)->assertSee('For sale')->assertSee('Sample listing');
        $this->get($host.'/en/listings?offering=rent')->assertOk()->assertSee('For rent')->assertDontSee('DEMO-1');
        $this->get($host.'/en/listings/DEMO-1')->assertOk()->assertSee('Bright 2BR')->assertSee('AED 2,450,000')->assertDontSee('application/ld+json');
        $this->get($host.'/ar/listings/DEMO-1')->assertOk()->assertSee('شقة مشرقة')->assertSee('درهم');
        $this->get($host.'/en/about')->assertOk()->assertSee('About Ahmed Al Falasi')->assertSee('Falasi Properties');
        $this->get($host.'/en/areas/marina')->assertOk()->assertSee('Marina');
        $this->get($host.'/en/areas/nowhere')->assertNotFound()->assertSee('Page not found');
        $this->get($host.'/en/contact')->assertOk()->assertSee('Contact Ahmed Al Falasi')->assertSee('+971');

        app(TenantLifecycle::class)->suspend($tenant);
        $this->get($host.'/en')->assertStatus(503)->assertSee('temporarily paused')->assertSee('Ahmed Al Falasi');
    });
}

it('lays marina out as a split hero with horizontal cards and palm as editorial, testimonials first', function (): void {
    themedTenant('marina', 'marina-agent');
    $marina = $this->get('http://marina-agent.example.test/en')->assertOk()->getContent();
    expect($marina)->toContain('class="split-hero"')->toContain('class="row-list"')->toContain('class="card flex min-w-0 overflow-hidden"');

    themedTenant('palm', 'palm-agent');
    $palm = $this->get('http://palm-agent.example.test/en')->assertOk()->getContent();
    expect($palm)->toContain('class="display')->toContain('gold-link')->not->toContain('split-hero');
    // testimonials come right after the hero, before featured listings (spec §10 "testimonials-forward")
    expect(strpos($palm, 'id="testimonials"'))->toBeLessThan((int) strpos($palm, 'id="featured"'));
});

it('keeps dark mode for atlas only', function (): void {
    $atlas = themedTenant('atlas', 'atlas-agent');
    $this->get('http://atlas-agent.example.test/en')->assertOk()->assertSee('data-dark="on"', false);
});
