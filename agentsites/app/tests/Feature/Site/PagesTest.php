<?php

declare(strict_types=1);

use App\Listings\DemoSeeder;
use App\Models\Listing;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Provisioning\TenantConfig;
use App\Tenancy\TenantContext;

// Spec §10 (theme atlas) and §16 (i18n): every page renders in both locales, RTL when Arabic,
// sections follow the config, demo listings behave as §14 says.

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, [
        'identity' => ['display_name' => 'Ahmed Al Falasi', 'agency_name' => 'Falasi Properties', 'tagline' => ['en' => 'Your partner in Dubai', 'ar' => 'شريكك في دبي']],
        'content' => ['service_areas' => ['Downtown', 'Marina']],
    ]);
    app(DemoSeeder::class)->seed($this->tenant);
    Testimonial::factory()->create(['tenant_id' => $this->tenant->id, 'author_name' => 'Fatima K.', 'text_en' => 'Found our villa in a week.', 'text_ar' => 'وجدنا فيلتنا خلال أسبوع.']);
});

it('renders the home page in English and Arabic with the right direction', function (): void {
    $this->get('http://ahmed.example.test/en')
        ->assertOk()
        ->assertSee('lang="en" dir="ltr"', false)
        ->assertSee('Your partner in Dubai')
        ->assertSee('Featured properties')
        ->assertSee('Downtown')
        ->assertSee('Found our villa in a week.')
        ->assertSee('Sample listing')
        ->assertSee('href="/ar"', false)
        ->assertSee('wa.me/971', false)
        ->assertSee('--c-primary:#8f6229', false);

    $this->get('http://ahmed.example.test/ar')
        ->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('شريكك في دبي')
        ->assertSee('عقارات مميزة')
        ->assertSee('وجدنا فيلتنا خلال أسبوع.')
        ->assertSee('عقار تجريبي')
        ->assertHeader('Content-Language', 'ar');
});

it('renders listings, a listing, about, an area and contact', function (): void {
    $this->get('http://ahmed.example.test/en/listings')->assertOk()->assertSee('DEMO-1')->assertSee('For sale');
    $this->get('http://ahmed.example.test/en/listings?offering=rent')->assertOk()->assertSee('For rent')->assertDontSee('DEMO-1');
    $this->get('http://ahmed.example.test/en/listings/DEMO-1')->assertOk()->assertSee('Bright 2BR')->assertSee('AED 2,450,000')->assertDontSee('application/ld+json');
    $this->get('http://ahmed.example.test/ar/listings/DEMO-1')->assertOk()->assertSee('شقة مشرقة')->assertSee('درهم');
    $this->get('http://ahmed.example.test/en/about')->assertOk()->assertSee('About Ahmed Al Falasi')->assertSee('Falasi Properties');
    $this->get('http://ahmed.example.test/en/areas/downtown')->assertOk()->assertSee('Properties in Downtown');
    $this->get('http://ahmed.example.test/en/areas/nowhere')->assertNotFound();
    $this->get('http://ahmed.example.test/en/contact')->assertOk()->assertSee('Contact Ahmed Al Falasi')->assertSee('+971');
});

it('drops demo listings as soon as a real one exists, and real listings carry structured data', function (): void {
    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'REAL-1', 'title_en' => 'Real 3BR in Marina', 'community' => 'Marina', 'featured' => true]);

    $this->get('http://ahmed.example.test/en/listings')->assertOk()->assertSee('REAL-1')->assertDontSee('DEMO-1');
    $this->get('http://ahmed.example.test/en')->assertOk()->assertSee('Real 3BR in Marina')->assertDontSee('Sample listing');
    $this->get('http://ahmed.example.test/en/listings/REAL-1')->assertOk()->assertSee('application/ld+json', false)->assertSee('RealEstateAgent');
    $this->get('http://ahmed.example.test/en/listings/DEMO-1')->assertNotFound();
});

it('toggles sections from the config', function (): void {
    $stored = $this->tenant->config;
    $stored['content']['sections'] = [['key' => 'hero', 'enabled' => true], ['key' => 'cta', 'enabled' => true]];
    app(TenantConfig::class)->save($this->tenant, $stored);

    $this->get('http://ahmed.example.test/en')
        ->assertOk()
        ->assertSee('Looking to buy, sell or rent?')
        ->assertDontSee('Featured properties')
        ->assertDontSee('What clients say');
});

it('uses the tenant palette and dark-mode setting', function (): void {
    $stored = $this->tenant->config;
    $stored['branding'] = ['palette' => 'navy', 'dark_mode' => 'on'];
    app(TenantConfig::class)->save($this->tenant, $stored);

    $this->get('http://ahmed.example.test/en')->assertOk()->assertSee('--c-primary:#1e3a8a', false)->assertSee('data-dark="on"', false);
});

it('marks a noindex site in the headers and the head', function (): void {
    $stored = $this->tenant->config;
    $stored['seo']['noindex'] = true;
    app(TenantConfig::class)->save($this->tenant, $stored);
    $this->get('http://ahmed.example.test/en')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('name="robots" content="noindex, nofollow"', false);

    $stored = $this->tenant->config;
    $stored['seo']['noindex'] = false;
    app(TenantConfig::class)->save($this->tenant, $stored);
    $this->get('http://ahmed.example.test/en')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertDontSee('name="robots"', false);
});

it('keeps two tenants completely apart over HTTP', function (): void {
    $other = $this->makeTenant('sara', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Sara Mansoori']]);
    Listing::factory()->create(['tenant_id' => $other->id, 'ref' => 'SARA-1', 'title_en' => 'Sara private listing']);

    $this->get('http://ahmed.example.test/en/listings/SARA-1')->assertNotFound();
    $this->get('http://ahmed.example.test/en/listings')->assertOk()->assertDontSee('Sara private listing');
    $this->get('http://sara.example.test/en/listings/SARA-1')->assertOk()->assertSee('Sara private listing')->assertDontSee('Ahmed Al Falasi');
    $this->get('http://sara.example.test/en/listings/DEMO-1')->assertNotFound();

    expect(TenantContext::current())->toBeNull();
});
