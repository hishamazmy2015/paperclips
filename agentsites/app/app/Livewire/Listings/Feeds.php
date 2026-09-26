<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Billing\PlanLimits;
use App\Listings\Feeds\FeedSync;
use App\Livewire\Concerns\OwnsTenant;
use App\Models\Listing;
use App\Models\ListingFeed;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Listing feeds (spec §14): connect GenericXml / Property Finder, filters, status, sync now. */
#[Layout('components.layouts.app')]
final class Feeds extends Component
{
    use OwnsTenant;

    public string $provider = 'generic_xml';

    public string $url = '';

    public string $authHeader = '';

    public string $authValue = '';

    public string $offering = '';

    public string $minPrice = '';

    public string $maxPrice = '';

    public string $propertyTypes = '';

    public string $communities = '';

    public string $error = '';

    /** @var array<string, mixed>|null */
    public ?array $lastResult = null;

    public function add(): void
    {
        $this->error = '';
        if (! in_array($this->provider, FeedSync::PROVIDERS, true)) {
            $this->error = __('platform.feeds.provider_invalid');

            return;
        }
        if (preg_match('#^https://#i', trim($this->url)) !== 1) {
            $this->error = __('platform.feeds.url_invalid');

            return;
        }
        if (! PlanLimits::feature($this->tenant()->account, 'feeds')) {
            $this->error = __('platform.feeds.plan_required');

            return;
        }

        $tenant = $this->tenant();
        TenantContext::with($tenant, function () use ($tenant): void {
            ListingFeed::query()->create([
                'tenant_id' => $tenant->id,
                'provider' => $this->provider,
                'credentials' => array_filter(['url' => trim($this->url), 'auth_header' => trim($this->authHeader), 'auth_value' => trim($this->authValue)]),
                'filters' => array_filter([
                    'offering' => $this->offering,
                    'min_price' => $this->minPrice !== '' ? (float) $this->minPrice : null,
                    'max_price' => $this->maxPrice !== '' ? (float) $this->maxPrice : null,
                    'property_types' => array_values(array_filter(array_map('trim', explode(',', strtolower($this->propertyTypes))))),
                    'communities' => array_values(array_filter(array_map('trim', explode(',', $this->communities)))),
                ], static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []),
                'schedule_minutes' => 30,
                'active' => true,
            ]);
        });
        $this->reset('url', 'authHeader', 'authValue', 'offering', 'minPrice', 'maxPrice', 'propertyTypes', 'communities');
    }

    public function syncNow(int $id): void
    {
        $feed = $this->feed($id);
        if ($feed !== null) {
            $this->lastResult = ['feed' => $id] + app(FeedSync::class)->sync($feed);
        }
    }

    public function toggle(int $id): void
    {
        $feed = $this->feed($id);
        if ($feed !== null) {
            $feed->active = ! $feed->active;
            $feed->save();
        }
    }

    public function remove(int $id): void
    {
        $this->feed($id)?->delete();
    }

    public function render(): View
    {
        $tenant = $this->tenant();
        $feeds = TenantContext::with($tenant, static fn () => ListingFeed::query()->orderBy('id')->get());
        $counts = TenantContext::with($tenant, static fn () => Listing::query()->whereNotNull('feed_id')->selectRaw('feed_id, count(*) as n')->groupBy('feed_id')->pluck('n', 'feed_id'));

        return view('livewire.listings.feeds', ['feeds' => $feeds, 'counts' => $counts, 'providers' => FeedSync::PROVIDERS])->title(__('platform.feeds.title'));
    }

    private function feed(int $id): ?ListingFeed
    {
        $tenant = $this->tenant();
        $feed = TenantContext::with($tenant, static fn (): ?ListingFeed => ListingFeed::query()->whereKey($id)->first());
        $feed?->setRelation('tenant', $tenant);

        return $feed;
    }
}
