<?php

declare(strict_types=1);

use App\Listings\DemoSeeder;
use App\Models\Listing;
use App\Models\Tenant;
use App\Provisioning\TenantConfig;

// Spec §16: sitemap + robots per tenant, JSON-LD, OG/Twitter, title pattern, hreflang, noindex.

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, [
        'identity' => ['display_name' => 'Ahmed Al Falasi', 'agency_name' => 'Falasi Properties', 'tagline' => ['en' => 'Your partner in Dubai', 'ar' => 'شريكك في دبي']],
        'content' => ['service_areas' => ['Downtown', 'Palm Jumeirah']],
        'contact' => ['socials' => ['instagram' => 'https://instagram.com/ahmed']],
    ]);
    app(DemoSeeder::class)->seed($this->tenant);
    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'REAL-1', 'title_en' => 'Real 3BR', 'community' => 'Downtown']);
});

it('publishes a sitemap with every page, locale alternates and real listings only', function (): void {
    $xml = $this->get('http://ahmed.example.test/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect($xml)->toContain('<loc>https://ahmed.example.test/en</loc>')
        ->toContain('<loc>https://ahmed.example.test/ar/listings</loc>')
        ->toContain('<loc>https://ahmed.example.test/en/areas/palm-jumeirah</loc>')
        ->toContain('<loc>https://ahmed.example.test/en/listings/REAL-1</loc>')
        ->toContain('hreflang="ar" href="https://ahmed.example.test/ar/listings/REAL-1"')
        ->not->toContain('DEMO-1');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('serves robots.txt that follows the site state', function (): void {
    $this->get('http://ahmed.example.test/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Allow: /')->assertSee('Sitemap: https://ahmed.example.test/sitemap.xml');

    app(TenantConfig::class)->save($this->tenant, array_replace_recursive($this->tenant->config, ['seo' => ['noindex' => true]]));
    $this->get('http://ahmed.example.test/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    expect($this->get('http://ahmed.example.test/sitemap.xml')->getContent())->not->toContain('<loc>');

    $draft = $this->makeTenant('draft', Tenant::STATUS_DRAFT);
    $this->get('http://draft.example.test/robots.txt?preview='.$draft->previewToken())->assertOk()->assertSee('Disallow: /');
});

it('carries structured data, social tags, hreflang and the title pattern on the home page', function (): void {
    $html = $this->get('http://ahmed.example.test/en')->assertOk()->getContent();

    expect($html)->toContain('<title>Ahmed Al Falasi — Falasi Properties | Downtown</title>')
        ->toContain('"@type":["RealEstateAgent","LocalBusiness"]')
        ->toContain('"name":"Ahmed Al Falasi"')
        ->toContain('"areaServed":[{"@type":"Place","name":"Downtown"}')
        ->toContain('"sameAs":["https://instagram.com/ahmed"]')
        ->toContain('hreflang="x-default" href="https://ahmed.example.test/en"')
        ->toContain('<meta name="twitter:card" content="summary">')
        ->not->toContain('og:image');

    app(TenantConfig::class)->save($this->tenant, array_replace_recursive($this->tenant->config, ['identity' => ['photo' => '/media/1/photo.webp'], 'seo' => ['title_pattern' => '{name} | {tagline}']]));
    $html = $this->get('http://ahmed.example.test/en')->assertOk()->getContent();
    expect($html)->toContain('<title>Ahmed Al Falasi | Your partner in Dubai</title>')
        ->toContain('<meta property="og:image" content="https://ahmed.example.test/media/1/photo.webp">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('"image":"https://ahmed.example.test/media/1/photo.webp"');

    $this->get('http://ahmed.example.test/en/listings')->assertSee('<title>Listings | Ahmed Al Falasi — Falasi Properties</title>', false);
    $this->get('http://ahmed.example.test/en/listings/REAL-1')->assertSee('"@type":"Offer"', false)->assertSee('"price":"', false);
    $this->get('http://ahmed.example.test/en/listings/DEMO-1')->assertDontSee('application/ld+json');
});

it('drops empty placeholders from the title pattern', function (): void {
    $solo = $this->makeTenant('solo', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Solo Agent']]);
    $this->get('http://solo.example.test/en')->assertSee('<title>Solo Agent</title>', false);
    app(TenantConfig::class)->save($solo, array_replace_recursive($solo->config, ['content' => ['service_areas' => ['Marina']]]));
    $this->get('http://solo.example.test/en')->assertSee('<title>Solo Agent | Marina</title>', false);
});
