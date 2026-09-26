<?php

declare(strict_types=1);

use App\Caching\PageCache;
use App\Caching\SiteWarmer;
use App\Models\Listing;
use App\Models\RegenerateRun;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Provisioning\TenantConfig;
use Illuminate\Support\Facades\Artisan;

// Spec §16: full-page cache keyed host+path+locale(+query), purged on publish/config/listing
// changes, ETag + Cache-Control on every page, 304 on If-None-Match; regenerate = purge + warm.

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Ahmed Al Falasi'], 'content' => ['service_areas' => ['Downtown']]]);
});

it('serves the second request from the cache with an ETag and answers 304 to If-None-Match', function (): void {
    $first = $this->get('http://ahmed.example.test/en')->assertOk()->assertHeader('X-Cache', 'MISS')->assertHeader('Cache-Control', 'max-age=60, public');
    $etag = $first->headers->get('ETag');
    expect($etag)->toMatch('/^"[0-9a-f]{32}"$/');

    $second = $this->get('http://ahmed.example.test/en')->assertOk()->assertHeader('X-Cache', 'HIT')->assertHeader('ETag', $etag)->assertHeader('Content-Language', 'en');
    // identical bodies (Livewire's asset injection can leak between tests in one process; it never runs on tenant pages)
    $normalize = static fn (string $html): string => trim((string) preg_replace(['/<!-- Livewire Styles -->.*?<\/style>/s', '/<script src="\/livewire\/[^>]*><\/script>/s', '/\s+/'], ['', '', ' '], $html));
    expect($normalize((string) $second->getContent()))->toBe($normalize((string) $first->getContent()));

    $this->get('http://ahmed.example.test/en', ['If-None-Match' => $etag])->assertStatus(304)->assertHeader('X-Cache', 'HIT');
    $this->get('http://ahmed.example.test/ar')->assertOk()->assertHeader('X-Cache', 'MISS')->assertSee('lang="ar" dir="rtl"', false);
});

it('normalises the query string and never caches drafts, previews or errors', function (): void {
    $this->get('http://ahmed.example.test/en/listings?type=villa&offering=sale')->assertOk()->assertHeader('X-Cache', 'MISS');
    $this->get('http://ahmed.example.test/en/listings?offering=sale&type=villa')->assertOk()->assertHeader('X-Cache', 'HIT');
    $this->get('http://ahmed.example.test/en/nowhere')->assertNotFound()->assertHeaderMissing('X-Cache');

    $draft = $this->makeTenant('draft', Tenant::STATUS_DRAFT, ['identity' => ['display_name' => 'Draft Agent']]);
    $this->get('http://draft.example.test/en?preview='.$draft->previewToken())->assertOk()->assertHeaderMissing('X-Cache');
    $this->get('http://draft.example.test/en?preview='.$draft->previewToken())->assertOk()->assertHeaderMissing('X-Cache');
    $this->get('http://ahmed.example.test/en?preview='.$this->tenant->previewToken())->assertOk()->assertHeaderMissing('X-Cache');
});

it('purges the tenant on config saves, listing and testimonial writes, and only that tenant', function (): void {
    $other = $this->makeTenant('sara', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Sara Mansoori']]);
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'MISS');
    $this->get('http://sara.example.test/en')->assertHeader('X-Cache', 'MISS');

    app(TenantConfig::class)->save($this->tenant, array_replace_recursive($this->tenant->config, ['identity' => ['tagline' => ['en' => 'Fresh tagline']]]));
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'MISS')->assertSee('Fresh tagline');
    $this->get('http://sara.example.test/en')->assertHeader('X-Cache', 'HIT');

    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'NEW-1', 'title_en' => 'Brand new villa']);
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'MISS');
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'HIT');

    Testimonial::factory()->create(['tenant_id' => $this->tenant->id, 'author_name' => 'Noor', 'text_en' => 'Great agent']);
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'MISS')->assertSee('Great agent');
    $this->get('http://sara.example.test/en')->assertHeader('X-Cache', 'HIT');

    app(PageCache::class)->purgeAll();
    $this->get('http://sara.example.test/en')->assertHeader('X-Cache', 'MISS');
});

it('regenerates one site: purge + warm every page in both locales, then visitors hit the cache', function (): void {
    $this->get('http://ahmed.example.test/en')->assertHeader('X-Cache', 'MISS');
    expect(SiteWarmer::paths($this->tenant))->toBe(['/ar', '/ar/listings', '/ar/about', '/ar/contact', '/ar/areas/downtown', '/en', '/en/listings', '/en/about', '/en/contact', '/en/areas/downtown']);

    Artisan::call('platform:site:regenerate', ['slug' => 'ahmed', '--json' => true]);
    $out = json_decode(Artisan::output(), true);
    expect($out)->toMatchArray(['ok' => true, 'slug' => 'ahmed', 'purged' => true, 'warmed' => 10]);

    foreach (['/en', '/ar/listings', '/en/areas/downtown'] as $path) {
        $this->get('http://ahmed.example.test'.$path)->assertOk()->assertHeader('X-Cache', 'HIT');
    }
    $this->get('http://ahmed.example.test/en?preview='.$this->tenant->previewToken())->assertOk();
});

it('regenerates every live site in batches with a checkpoint and resumes after a kill', function (): void {
    $this->makeTenant('sara', Tenant::STATUS_LIVE);
    $this->makeTenant('omar', Tenant::STATUS_LIVE);
    $this->makeTenant('draft', Tenant::STATUS_DRAFT);

    Artisan::call('platform:site:regenerate', ['--all' => true, '--batch' => 2, '--json' => true]);
    $run = json_decode(Artisan::output(), true);
    expect($run)->toMatchArray(['status' => RegenerateRun::STATUS_COMPLETED, 'total_tenants' => 3, 'processed_tenants' => 3])
        ->and($run['warmed_pages'])->toBe(10 + 8 + 8);
    $this->get('http://omar.example.test/en')->assertHeader('X-Cache', 'HIT');

    // a killed run: the checkpoint says the first site is done
    $killed = RegenerateRun::query()->create(['status' => RegenerateRun::STATUS_FAILED, 'total_tenants' => 3, 'processed_tenants' => 1, 'warmed_pages' => 10, 'last_tenant_id' => $this->tenant->id, 'batch_size' => 2, 'warm' => false, 'started_at' => now()]);
    Artisan::call('platform:site:regenerate', ['--resume' => $killed->id, '--json' => true]);
    $resumed = json_decode(Artisan::output(), true);
    expect($resumed)->toMatchArray(['id' => $killed->id, 'status' => RegenerateRun::STATUS_COMPLETED, 'processed_tenants' => 3]);

    Artisan::call('platform:site:regenerate', ['--status' => $killed->id, '--json' => true]);
    expect(json_decode(Artisan::output(), true)['processed_tenants'])->toBe(3);
    expect(Artisan::call('platform:site:regenerate', ['--status' => 999]))->toBe(1);
    expect(Artisan::call('platform:site:regenerate'))->toBe(1);
});
