<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Tenant;
use App\Provisioning\TenantLifecycle;
use App\Tenancy\RedirectRules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

// Spec §11 as HTTP behaviour: what a browser gets for each kind of host and tenant status.

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Ahmed Al Falasi']]);
});

it('redirects the site root to the locale and serves the home page', function (): void {
    $this->get('http://ahmed.example.test/')->assertRedirect('/en');
    $this->get('http://ahmed.example.test/', ['Accept-Language' => 'ar,en;q=0.8'])->assertRedirect('/ar');

    $this->get('http://ahmed.example.test/en')
        ->assertOk()
        ->assertSee('Ahmed Al Falasi')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Language', 'en');
});

it('serves repeat requests from the host cache without a database lookup for the host', function (): void {
    $this->get('http://ahmed.example.test/en')->assertOk();

    DB::enableQueryLog();
    $this->get('http://ahmed.example.test/en/contact')->assertOk();
    $hostQueries = array_filter(DB::getQueryLog(), fn (array $q): bool => str_contains($q['query'], '"domains"'));
    expect($hostQueries)->toBeEmpty();
});

it('returns 404 for hosts nobody owns and for platform hosts hitting site routes', function (): void {
    $this->get('http://nobody.example.test/en')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('http://app.example.test/en/listings')->assertNotFound();
});

it('follows redirect rules with path and query preserved', function (): void {
    app(RedirectRules::class)->add('old.example.test', 'ahmed.example.test');

    $this->get('http://old.example.test/en/listings?offering=rent')
        ->assertStatus(301)
        ->assertRedirect('https://ahmed.example.test/en/listings?offering=rent');
});

it('301s an alias host to the primary host', function (): void {
    TenantContext::with($this->tenant, fn () => Domain::query()->create(['host' => 'www.ahmedhomes.ae', 'type' => 'custom', 'role' => 'alias', 'verified' => true]));

    $this->get('http://www.ahmedhomes.ae/ar/about')->assertStatus(301)->assertRedirect('https://ahmed.example.test/ar/about');
});

it('shows the paused page with 503, noindex and Retry-After for a suspended site', function (): void {
    app(TenantLifecycle::class)->suspend($this->tenant);

    $this->get('http://ahmed.example.test/en')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '3600')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('temporarily paused');
});

it('hides a draft site unless the preview token is presented, then remembers it in the session', function (): void {
    $draft = $this->makeTenant('draft-site', Tenant::STATUS_DRAFT);

    $this->get('http://draft-site.example.test/en')->assertNotFound();
    $this->get('http://draft-site.example.test/en?preview=wrong')->assertNotFound();

    $this->get('http://draft-site.example.test/en?preview='.$draft->previewToken())
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('http://draft-site.example.test/en/listings')->assertOk();
});

it('returns 404 for a deleted tenant', function (): void {
    app(TenantLifecycle::class)->delete($this->tenant);

    $this->get('http://ahmed.example.test/en')->assertNotFound();
});

it('serves the landing page on platform hosts', function (): void {
    $this->get('http://example.test/')->assertOk()->assertSee('live in 2 minutes');
    $this->get('http://app.example.test/')->assertOk();
    $this->get('http://app.example.test/?lang=ar')->assertOk()->assertSee('دقيقتين');
});

it('renders the themed 404 inside a tenant site', function (): void {
    $this->get('http://ahmed.example.test/en/no-such-page')->assertNotFound()->assertSee('Ahmed Al Falasi')->assertSee('Page not found');
    $this->get('http://ahmed.example.test/ar/listings/NOPE')->assertNotFound()->assertSee('الصفحة غير موجودة');
});

it('answers the on-demand TLS check only through the loopback listener', function (): void {
    TenantContext::with($this->tenant, fn () => Domain::query()->create(['host' => 'ahmedhomes.ae', 'type' => 'custom', 'role' => 'alias', 'verified' => true]));
    TenantContext::with($this->tenant, fn () => Domain::query()->create(['host' => 'pending.ahmedhomes.ae', 'type' => 'custom', 'role' => 'alias', 'verified' => false]));

    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=ahmedhomes.ae')->assertOk()->assertSee('ok');
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=AHMEDHOMES.AE')->assertOk();
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=ahmed.example.test')->assertOk();
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=pending.ahmedhomes.ae')->assertNotFound();
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=nobody.ae')->assertNotFound();
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=')->assertNotFound();
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=not a host')->assertNotFound();

    // the public hosts never expose it (Caddy also answers 404 for /internal/* on public blocks)
    $this->get('http://app.example.test/internal/tls/allow?domain=ahmedhomes.ae')->assertNotFound();
    $this->get('http://ahmed.example.test/internal/tls/allow?domain=ahmedhomes.ae')->assertNotFound();

    app(TenantLifecycle::class)->suspend($this->tenant);
    $this->get('http://127.0.0.1:9080/internal/tls/allow?domain=ahmedhomes.ae')->assertNotFound();
});
