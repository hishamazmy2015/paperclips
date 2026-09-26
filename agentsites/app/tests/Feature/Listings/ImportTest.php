<?php

declare(strict_types=1);

use App\Listings\ListingImporter;
use App\Models\Listing;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Spec §14: CSV import with template and row errors; dedupe by ref; photos cached locally.

function csvOf(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'listings').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['ref', 'title_en', 'title_ar', 'offering', 'property_type', 'price', 'currency', 'bedrooms', 'bathrooms', 'area_sqft', 'community', 'city', 'status', 'featured', 'description_en', 'description_ar', 'media'], escape: '');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '');
    }
    fclose($handle);

    return $path;
}

function png(int $w = 300, int $h = 200): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 30, 58, 138));
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    Storage::fake('media');
    Http::fake([
        'photos.example.com/*' => Http::response(png(), 200, ['Content-Type' => 'image/png']),
        'missing.example.com/*' => Http::response('nope', 404),
    ]);
    $this->tenant = $this->makeTenant('ahmed');
});

it('creates listings from a CSV, caches photos as variants, reports bad rows and dedupes on re-import', function (): void {
    $path = csvOf([
        ['FP-1001', '2BR Downtown', 'شقة', 'sale', 'apartment', '2450000', 'AED', '2', '2', '1350', 'Downtown', 'Dubai', 'available', 'true', 'Bright.', 'مشرقة', 'https://photos.example.com/a.jpg;https://photos.example.com/b.jpg'],
        ['FP-1002', 'Studio', '', 'swap', 'studio', '72000', 'AED', '0', '1', '480', 'Marina', 'Dubai', '', '', '', '', ''],
        ['FP-1003', 'Villa', '', 'rent', 'villa', '185000', 'AED', '3', '4', '', 'JVC', 'Dubai', '', '', '', '', 'https://missing.example.com/gone.jpg'],
        ['FP-1001', 'Duplicate', '', 'sale', 'apartment', '1', 'AED', '', '', '', '', '', '', '', '', '', ''],
    ]);

    $result = app(ListingImporter::class)->importCsv($this->tenant, $path);
    expect($result)->toMatchArray(['total' => 4, 'created' => 2, 'updated' => 0])
        ->and(array_column($result['errors'], 'row'))->toBe([3, 5])
        ->and($result['errors'][0]['error'])->toContain('offering')
        ->and($result['errors'][1]['error'])->toContain('duplicate')
        ->and(file_get_contents((string) $result['errors_path']))->toStartWith("row,ref,error\n3,FP-1002,");

    $listing = TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'FP-1001')->firstOrFail());
    expect($listing->source)->toBe('csv')->and($listing->featured)->toBeTrue()->and($listing->imageSets())->toHaveCount(2);
    $set = $listing->imageSets()[0];
    expect($set['card'])->toStartWith('/media/'.$this->tenant->id.'/listings/fp-1001/photo-card-')->and($set['hero'])->toContain('-hero-')->and($set['thumb'])->toContain('-thumb-');
    foreach ($set as $variant) {
        expect(Storage::disk('media')->exists(substr($variant, strlen('/media/'))))->toBeTrue();
    }
    expect($listing->getAttribute('media')[0]['remote'])->toBe('https://photos.example.com/a.jpg');
    Http::assertSentCount(3);

    // an unreachable photo keeps its URL (logged), the listing is still created
    $villa = TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'FP-1003')->firstOrFail());
    expect($villa->images())->toBe(['https://missing.example.com/gone.jpg']);

    // the site shows the cached card image and the new listing is not cached stale
    $this->get('http://ahmed.example.test/en/listings')->assertOk()->assertSee('2BR Downtown')->assertSee($set['card'], false)->assertDontSee('Sample listing');
    $this->get('http://ahmed.example.test/en/listings/FP-1001')->assertOk()->assertSee($set['hero'], false)->assertSee('"@type":"Offer"', false);

    // re-import: same refs update, cached photos are reused without a download
    $again = csvOf([['FP-1001', '2BR Downtown (renamed)', '', 'sale', 'apartment', '2500000', 'AED', '2', '2', '1350', 'Downtown', 'Dubai', 'available', 'false', '', '', 'https://photos.example.com/a.jpg;https://photos.example.com/b.jpg']]);
    $result = app(ListingImporter::class)->importCsv($this->tenant, $again);
    expect($result)->toMatchArray(['total' => 1, 'created' => 0, 'updated' => 1, 'errors' => []]);
    Http::assertSentCount(3);
    $listing = $listing->fresh();
    expect($listing->title_en)->toBe('2BR Downtown (renamed)')->and((float) $listing->price)->toBe(2500000.0)->and($listing->featured)->toBeFalse()->and($listing->imageSets())->toHaveCount(2);
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->real()->count()))->toBe(2);
});

it('validates only in a dry run and respects the plan limit', function (): void {
    config(['plans.plans.trial.limits.listings' => 1]);
    $path = csvOf([
        ['A-1', 'One', '', 'sale', 'villa', '100', 'AED', '', '', '', '', '', '', '', '', '', ''],
        ['A-2', 'Two', '', 'sale', 'villa', '100', 'AED', '', '', '', '', '', '', '', '', '', ''],
    ]);

    $dry = app(ListingImporter::class)->importCsv($this->tenant, $path, dryRun: true);
    expect($dry)->toMatchArray(['total' => 2, 'created' => 1, 'updated' => 0])
        ->and($dry['errors'][0]['error'])->toContain('plan limit')
        ->and($dry['errors_path'])->toBeNull()
        ->and(TenantContext::with($this->tenant, fn () => Listing::query()->real()->count()))->toBe(0);

    $real = app(ListingImporter::class)->importCsv($this->tenant, $path);
    expect($real['created'])->toBe(1)->and($real['errors'][0]['ref'])->toBe('A-2');
    expect(app(ListingImporter::class)->importCsv($this->tenant, '/nowhere.csv')['errors'][0]['error'])->toContain('cannot read');
});

it('imports from the command line', function (): void {
    $path = csvOf([['C-1', 'CLI listing', '', 'rent', 'townhouse', '150000', 'AED', '3', '', '', 'JVC', 'Dubai', '', '', '', '', '']]);

    Artisan::call('platform:listings:import', ['slug' => 'ahmed', 'file' => $path, '--json' => true]);
    expect(json_decode(Artisan::output(), true))->toMatchArray(['ok' => true, 'slug' => 'ahmed', 'created' => 1, 'updated' => 0, 'dry_run' => false]);
    Artisan::call('platform:listings:import', ['slug' => 'ahmed', 'file' => $path, '--dry-run' => true]);
    expect(Artisan::output())->toContain('1 rows — 0 created, 1 updated, 0 errors');
    expect(Artisan::call('platform:listings:import', ['slug' => 'nobody', 'file' => $path]))->toBe(1);
    expect(Artisan::call('platform:listings:import', ['slug' => 'ahmed', 'file' => '/nowhere.csv']))->toBe(1);
});

it('keeps imports inside their own tenant', function (): void {
    $other = $this->makeTenant('sara');
    app(ListingImporter::class)->importCsv($this->tenant, csvOf([['X-1', 'Mine', '', 'sale', 'villa', '100', 'AED', '', '', '', '', '', '', '', '', '', '']]));

    expect(TenantContext::with($other, fn () => Listing::query()->where('ref', 'X-1')->exists()))->toBeFalse()
        ->and(TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'X-1')->exists()))->toBeTrue();
    $this->get('http://sara.example.test/en/listings')->assertOk()->assertDontSee('Mine');
});
