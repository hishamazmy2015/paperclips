<?php

declare(strict_types=1);

use App\Jobs\SyncDueFeeds;
use App\Jobs\SyncFeed;
use App\Listings\Feeds\FeedException;
use App\Listings\Feeds\FeedSync;
use App\Models\Listing;
use App\Models\ListingFeed;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Spec §14: feeds via adapters (GenericXml, PropertyFinder), every 30 min, per-agent filters,
// dedupe by ref, media cached locally, status shown; a failing feed keeps the last good data.

function genericXml(array $listings): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?><listings>';
    foreach ($listings as $l) {
        $xml .= '<listing>';
        foreach ($l as $key => $value) {
            if ($key === 'images') {
                $xml .= '<images>'.implode('', array_map(fn (string $u): string => '<image>'.$u.'</image>', $value)).'</images>';
            } else {
                $xml .= "<{$key}>".htmlspecialchars((string) $value, ENT_XML1)."</{$key}>";
            }
        }
        $xml .= '</listing>';
    }

    return $xml.'</listings>';
}

function makeFeed(Tenant $tenant, string $provider = 'generic_xml', array $filters = [], string $url = 'https://feeds.example.com/agent.xml'): ListingFeed
{
    return TenantContext::with($tenant, fn (): ListingFeed => ListingFeed::query()->create([
        'tenant_id' => $tenant->id,
        'provider' => $provider,
        'credentials' => ['url' => $url, 'auth_header' => 'X-Api-Key', 'auth_value' => 'secret-key'],
        'filters' => $filters,
        'schedule_minutes' => 30,
        'active' => true,
    ]));
}

beforeEach(function (): void {
    Storage::fake('media');
    $this->tenant = $this->makeTenant('ahmed');
    $this->three = [
        ['ref' => 'G-1', 'title_en' => 'Feed villa', 'offering' => 'sale', 'property_type' => 'villa', 'price' => '5000000', 'community' => 'Dubai Hills', 'city' => 'Dubai', 'images' => ['https://photos.example.com/g1.jpg']],
        ['ref' => 'G-2', 'title_en' => 'Feed flat', 'offering' => 'rent', 'property_type' => 'apartment', 'price' => '120000', 'bedrooms' => '2', 'community' => 'Marina'],
        ['ref' => 'G-3', 'title_en' => 'Feed studio', 'offering' => 'rent', 'property_type' => 'studio', 'price' => '60000', 'bedrooms' => '0', 'community' => 'JVC', 'media' => 'https://photos.example.com/g3a.jpg;https://photos.example.com/g3b.jpg'],
    ];
});

it('syncs a GenericXml feed: creates, caches media, hides what disappears, records status', function (): void {
    $img = imagecreatetruecolor(50, 50);
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    Http::fake([
        'feeds.example.com/agent.xml' => Http::sequence()->push(genericXml($this->three))->push(genericXml(array_slice($this->three, 0, 2)))->push('<listings><broken', 200)->push('', 500),
        'photos.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
    $feed = makeFeed($this->tenant);

    $result = app(FeedSync::class)->sync($feed);
    expect($result)->toMatchArray(['fetched' => 3, 'created' => 3, 'updated' => 0, 'hidden' => 0, 'skipped' => 0, 'errors' => []]);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://feeds.example.com/agent.xml' && $request->hasHeader('X-Api-Key', 'secret-key'));
    $listings = TenantContext::with($this->tenant, fn () => Listing::query()->real()->orderBy('ref')->get());
    expect($listings->pluck('ref')->all())->toBe(['G-1', 'G-2', 'G-3'])
        ->and($listings[0]->source)->toBe('feed')->and($listings[0]->feed_id)->toBe($feed->id)->and($listings[0]->feed_ref)->toBe('G-1')
        ->and($listings[0]->imageSets()[0]['card'])->toStartWith('/media/'.$this->tenant->id.'/listings/g-1/')
        ->and($listings[2]->imageSets())->toHaveCount(2);
    expect($feed->fresh()->last_sync_at)->not->toBeNull()->and($feed->fresh()->last_error)->toBeNull();

    // the feed drops G-3 → hidden, not deleted; visitors no longer see it
    $result = app(FeedSync::class)->sync($feed);
    expect($result)->toMatchArray(['fetched' => 2, 'created' => 0, 'updated' => 2, 'hidden' => 1]);
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'G-3')->value('status')))->toBe('hidden');
    $this->get('http://ahmed.example.test/en/listings')->assertOk()->assertSee('Feed villa')->assertDontSee('Feed studio');

    // broken XML and a 500: the error is recorded, nothing is touched
    $result = app(FeedSync::class)->sync($feed);
    expect($result['errors'][0])->toBe('invalid XML')->and($feed->fresh()->last_error)->toBe('invalid XML');
    $result = app(FeedSync::class)->sync($feed);
    expect($feed->fresh()->last_error)->toContain('HTTP 500');
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->where('status', 'available')->count()))->toBe(2);
});

it('applies the per-agent filters and the plan limit', function (): void {
    Http::fake(['feeds.example.com/*' => Http::response(genericXml($this->three))]);
    $feed = makeFeed($this->tenant, filters: ['offering' => 'rent', 'max_price' => 100000, 'property_types' => ['studio', 'apartment'], 'communities' => ['jvc']]);

    $result = app(FeedSync::class)->sync($feed, fetchMedia: false);
    expect($result)->toMatchArray(['fetched' => 3, 'created' => 1, 'skipped' => 2]);
    expect(TenantContext::with($this->tenant, fn () => Listing::query()->real()->pluck('ref')->all()))->toBe(['G-3']);

    config(['plans.plans.trial.limits.listings' => 1]);
    $open = makeFeed($this->tenant, url: 'https://feeds.example.com/open.xml');
    $result = app(FeedSync::class)->sync($open, fetchMedia: false);
    expect($result['created'])->toBe(0)->and($result['updated'])->toBe(1)->and($result['errors'])->toHaveCount(2)->and($result['errors'][0])->toContain('plan limit');
});

it('reads a Property Finder export', function (): void {
    $xml = <<<'XML'
    <?xml version="1.0"?><list last_update="2026-09-26"><property>
      <reference_number>PF-77</reference_number><offering_type>RR</offering_type><property_type>AP</property_type>
      <title_en>Marina 2BR</title_en><title_ar>شقة المارينا</title_ar><description_en>Sea view.</description_en>
      <price><yearly>140000</yearly></price><bedroom>2</bedroom><bathroom>2</bathroom><size>1200</size>
      <community>Dubai Marina</community><sub_community>Marina Gate</sub_community><city>Dubai</city>
      <photo><url>https://photos.example.com/pf1.jpg</url><url>https://photos.example.com/pf2.jpg</url></photo>
    </property><property>
      <reference_number>PF-78</reference_number><offering_type>CS</offering_type><property_type>ST</property_type>
      <title_en>Studio</title_en><price>600000</price><bedroom>studio</bedroom><size>450</size><sub_community>JVC</sub_community>
    </property></list>
    XML;
    Http::fake(['feeds.example.com/*' => Http::response($xml)]);
    $feed = makeFeed($this->tenant, 'propertyfinder');

    $result = app(FeedSync::class)->sync($feed, fetchMedia: false);
    expect($result)->toMatchArray(['fetched' => 2, 'created' => 2, 'errors' => []]);
    $flat = TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'PF-77')->firstOrFail());
    expect($flat->offering)->toBe('rent')->and($flat->property_type)->toBe('apartment')->and((float) $flat->price)->toBe(140000.0)->and($flat->bedrooms)->toBe(2)->and($flat->community)->toBe('Dubai Marina')->and($flat->title_ar)->toBe('شقة المارينا')->and($flat->images())->toBe(['https://photos.example.com/pf1.jpg', 'https://photos.example.com/pf2.jpg']);
    $studio = TenantContext::with($this->tenant, fn () => Listing::query()->where('ref', 'PF-78')->firstOrFail());
    expect($studio->offering)->toBe('sale')->and($studio->property_type)->toBe('studio')->and($studio->bedrooms)->toBe(0)->and($studio->community)->toBe('JVC');
});

it('queues only the feeds that are due, and runs them from the command line', function (): void {
    Bus::fake([SyncFeed::class]);
    $never = makeFeed($this->tenant);
    $recent = makeFeed($this->tenant, url: 'https://feeds.example.com/b.xml');
    $recent->update(['last_sync_at' => now()->subMinutes(10)]);
    $stale = makeFeed($this->tenant, url: 'https://feeds.example.com/c.xml');
    $stale->update(['last_sync_at' => now()->subMinutes(45)]);
    $off = makeFeed($this->tenant, url: 'https://feeds.example.com/d.xml');
    $off->update(['active' => false]);

    expect((new SyncDueFeeds)->handle())->toBe(2);
    Bus::assertDispatched(SyncFeed::class, fn (SyncFeed $job): bool => $job->feedId === $never->id);
    Bus::assertDispatched(SyncFeed::class, fn (SyncFeed $job): bool => $job->feedId === $stale->id);
    Bus::assertNotDispatched(SyncFeed::class, fn (SyncFeed $job): bool => $job->feedId === $recent->id || $job->feedId === $off->id);

    Http::fake(['feeds.example.com/*' => Http::response(genericXml(array_slice($this->three, 1, 1)))]);
    Artisan::call('platform:feeds:sync', ['--feed' => $never->id, '--json' => true, '--no-media' => true]);
    $out = json_decode(Artisan::output(), true);
    expect($out['results'][0])->toMatchArray(['feed' => $never->id, 'tenant' => 'ahmed', 'provider' => 'generic_xml', 'created' => 1]);
    Artisan::call('platform:feeds:sync', ['--tenant' => 'ahmed', '--no-media' => true]);
    expect(Artisan::output())->toContain('feed #'.$never->id)->not->toContain('feed #'.$off->id); // --tenant runs active feeds only
    expect(Artisan::call('platform:feeds:sync'))->toBe(1);
    Artisan::call('platform:feeds:sync', ['--due' => true, '--json' => true]);
    expect(json_decode(Artisan::output(), true)['ok'])->toBeTrue();

    // the queued job itself skips inactive feeds and syncs active ones
    (new SyncFeed($off->id))->handle(app(FeedSync::class));
    expect($off->fresh()->last_sync_at)->toBeNull();
    (new SyncFeed($stale->id))->handle(app(FeedSync::class));
    expect($stale->fresh()->last_sync_at->gt(now()->subMinute()))->toBeTrue();

    expect(fn () => FeedSync::adapter('bayut'))->toThrow(FeedException::class);
    $noUrl = TenantContext::with($this->tenant, fn (): ListingFeed => ListingFeed::query()->create(['tenant_id' => $this->tenant->id, 'provider' => 'generic_xml', 'credentials' => [], 'active' => true]));
    expect(app(FeedSync::class)->sync($noUrl)['errors'][0])->toContain('feed URL');
});
