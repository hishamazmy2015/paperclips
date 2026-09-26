<?php

declare(strict_types=1);

use App\Listings\DemoSeeder;
use App\Livewire\Listings\Feeds;
use App\Livewire\Listings\Form;
use App\Livewire\Listings\Import;
use App\Livewire\Listings\Index;
use App\Models\Listing;
use App\Models\ListingFeed;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Spec §14: manual form with photos and order, CSV import page, feeds page — all on the app
// host, all inside the signed-in agent's own tenant.

beforeEach(function (): void {
    Storage::fake('media');
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Ahmed Al Falasi']]);
    $this->tenant->update(['account_id' => $this->user->account_id]);
    app(DemoSeeder::class)->seed($this->tenant);
    $this->actingAs($this->user);
});

function fakeJpeg(int $w = 640, int $h = 480): UploadedFile
{
    return UploadedFile::fake()->image('photo.jpg', $w, $h);
}

it('creates a listing with photos in order, shows it on the site and lists it', function (): void {
    Livewire::test(Form::class)
        ->assertSet('ref', 'REF-0001')
        ->set('title_en', 'Sea view 2BR')
        ->set('title_ar', 'شقة بإطلالة بحرية')
        ->set('offering', 'rent')
        ->set('property_type', 'apartment')
        ->set('price', '120,000')
        ->set('bedrooms', '2')
        ->set('community', 'Marina')
        ->set('lat', '25.08')
        ->set('lng', '55.14')
        ->set('featured', true)
        ->set('photos', [fakeJpeg(800, 600), fakeJpeg(300, 300)])
        ->assertCount('media', 2)
        ->call('move', 1, -1)
        ->call('save')
        ->assertSet('problems', [])
        ->assertRedirect(route('listings'));

    $listing = TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'REF-0001')->firstOrFail());
    expect($listing->source)->toBe('manual')->and((float) $listing->price)->toBe(120000.0)->and($listing->offering)->toBe('rent')->and($listing->lat)->toBe(25.08)->and($listing->featured)->toBeTrue()
        ->and($listing->imageSets())->toHaveCount(2)
        ->and($listing->getAttribute('media')[0]['width'])->toBe(300); // moved to the front
    $cover = $listing->coverImage();
    expect(Storage::disk('media')->exists(substr((string) $cover, strlen('/media/'))))->toBeTrue();

    $this->get('http://ahmed.example.test/en')->assertOk()->assertSee('Sea view 2BR')->assertDontSee('Sample listing');
    $this->get('http://app.example.test/listings')->assertOk()->assertSee('Sea view 2BR')->assertSee('1 of 100 listings')->assertSee('Listing saved');

    // edit: rename, drop the cover, reorder by drag
    Livewire::test(Form::class, ['listing' => $listing->id])
        ->assertSet('title_en', 'Sea view 2BR')
        ->assertSet('price', '120000')
        ->set('title_en', 'Sea view 2BR, upgraded')
        ->call('removePhoto', 0)
        ->assertCount('media', 1)
        ->set('photos', [fakeJpeg(500, 500)])
        ->call('reorder', [1, 0])
        ->call('save')
        ->assertRedirect(route('listings'));
    $listing = $listing->fresh();
    expect($listing->title_en)->toBe('Sea view 2BR, upgraded')->and($listing->getAttribute('media')[0]['width'])->toBe(500);
});

it('rejects bad input, duplicate refs, a half pin and the plan limit', function (): void {
    $component = Livewire::test(Form::class)->set('title_en', '')->set('price', '0')->set('lat', '25')->call('save');
    expect($component->get('problems'))->toHaveCount(3)->and(implode(' ', $component->get('problems')))->toContain('title_en')->toContain('price')->toContain('latitude');

    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'TAKEN']);
    $component = Livewire::test(Form::class)->set('ref', 'TAKEN')->set('title_en', 'Dup')->set('price', '10')->call('save');
    expect($component->get('problems'))->toBe([__('platform.listings.ref_taken')]);

    config(['plans.plans.trial.limits.listings' => 1]);
    $component = Livewire::test(Form::class)->set('ref', 'MORE')->set('title_en', 'One too many')->set('price', '10')->call('save');
    expect($component->get('problems')[0])->toContain('1 listings');

    Livewire::test(Form::class, ['listing' => 999999])->assertStatus(404);
});

it('toggles featured and hidden, searches and deletes from the index', function (): void {
    $a = Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'A-1', 'title_en' => 'Alpha villa']);
    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'B-1', 'title_en' => 'Beta flat']);
    $foreign = $this->makeTenant('sara');
    $theirs = Listing::factory()->create(['tenant_id' => $foreign->id, 'ref' => 'S-1', 'title_en' => 'Sara private']);

    Livewire::test(Index::class)
        ->assertSee('Alpha villa')->assertSee('Beta flat')->assertDontSee('Sara private')
        ->set('search', 'alpha')->assertSee('Alpha villa')->assertDontSee('Beta flat')
        ->call('toggleFeatured', $a->id)
        ->call('toggleHidden', $a->id)
        ->call('delete', $theirs->id)
        ->call('toggleFeatured', $theirs->id);

    expect($a->fresh()->featured)->toBeTrue()->and($a->fresh()->status)->toBe('hidden')
        ->and($theirs->fresh())->not->toBeNull()->and($theirs->fresh()->featured)->toBeFalse();

    Livewire::test(Index::class)->call('delete', $a->id);
    expect($a->fresh()->trashed())->toBeTrue();
});

it('imports a CSV from the page after a dry-run preview', function (): void {
    $csv = "ref,title_en,offering,property_type,price\nC-1,Imported villa,sale,villa,900000\nC-2,Broken,swap,villa,1\n";
    $file = UploadedFile::fake()->createWithContent('listings.csv', $csv);

    $component = Livewire::test(Import::class)->set('file', $file);
    expect($component->get('preview'))->toMatchArray(['total' => 2, 'created' => 1, 'updated' => 0])->and($component->get('preview')['errors'][0]['row'])->toBe(3);
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->real()->count()))->toBe(0);

    $component->call('import');
    expect($component->get('result'))->toMatchArray(['created' => 1, 'updated' => 0])->and($component->get('preview'))->toBeNull();
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'C-1')->exists()))->toBeTrue();

    $this->get('http://app.example.test/listings/template.csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSee('ref,title_en,title_ar');
});

it('connects, syncs, pauses and removes feeds from the page', function (): void {
    Http::fake(['feeds.example.com/*' => Http::response('<listings><listing><ref>F-1</ref><title_en>Fed villa</title_en><offering>sale</offering><property_type>villa</property_type><price>100</price></listing></listings>')]);

    $component = Livewire::test(Feeds::class)
        ->assertSee('No feed connected yet')
        ->set('url', 'http://insecure.example.com/x.xml')->call('add')->assertSet('error', __('platform.feeds.url_invalid'))
        ->set('url', 'https://feeds.example.com/agent.xml')->set('authHeader', 'X-Api-Key')->set('authValue', 'k')->set('offering', 'sale')->set('communities', 'Downtown, Marina')->call('add')->assertSet('error', '')
        ->assertSee('Generic XML')->assertSee('not synced yet');

    $feed = TenantContext::with($this->tenant, fn () => ListingFeed::query()->firstOrFail());
    expect($feed->credentials)->toBe(['url' => 'https://feeds.example.com/agent.xml', 'auth_header' => 'X-Api-Key', 'auth_value' => 'k'])
        ->and($feed->filters)->toBe(['offering' => 'sale', 'communities' => ['Downtown', 'Marina']]);

    $component->call('syncNow', $feed->id);
    expect($component->get('lastResult'))->toMatchArray(['feed' => $feed->id, 'fetched' => 1, 'skipped' => 1]); // filtered out by community
    $component->assertSee('1 fetched');

    $component->call('toggle', $feed->id);
    expect($feed->fresh()->active)->toBeFalse();
    $component->call('remove', $feed->id);
    expect($feed->fresh()->trashed())->toBeTrue();

    $this->tenant->account->update(['plan_key' => 'starter']);
    Livewire::test(Feeds::class)->set('url', 'https://feeds.example.com/a.xml')->call('add')->assertSet('error', __('platform.feeds.plan_required'));
});

it('renders the listing screens over HTTP for the owner only', function (): void {
    $this->get('http://app.example.test/listings')->assertOk()->assertSee('Listings');
    $this->get('http://app.example.test/listings/new')->assertOk()->assertSee('Add a listing');
    $this->get('http://app.example.test/listings/import')->assertOk()->assertSee('Download the template');
    $this->get('http://app.example.test/listings/feeds')->assertOk()->assertSee('Connect a feed');
    $this->get('http://app.example.test/home')->assertOk()->assertSee('data-test="nav-listings"', false);

    auth()->logout();
    $this->get('http://app.example.test/listings')->assertRedirect('http://app.example.test/start');
});
