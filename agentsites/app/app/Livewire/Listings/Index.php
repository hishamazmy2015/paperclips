<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Billing\PlanLimits;
use App\Livewire\Concerns\OwnsTenant;
use App\Models\Listing;
use App\Platform\Hosts;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The agent's listings (spec §14): search, featured/hidden toggles, delete, plan usage. */
#[Layout('components.layouts.app')]
final class Index extends Component
{
    use OwnsTenant, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function toggleFeatured(int $id): void
    {
        $this->update($id, static function (Listing $listing): void {
            $listing->featured = ! $listing->featured;
        });
    }

    public function toggleHidden(int $id): void
    {
        $this->update($id, static function (Listing $listing): void {
            $listing->status = $listing->status === 'hidden' ? Listing::STATUS_AVAILABLE : 'hidden';
        });
    }

    public function delete(int $id): void
    {
        TenantContext::with($this->tenant(), static function () use ($id): void {
            Listing::query()->whereKey($id)->real()->first()?->delete();
        });
    }

    public function render(): View
    {
        $tenant = $this->tenant();
        $listings = TenantContext::with($tenant, function () {
            return Listing::query()->real()
                ->when(trim($this->search) !== '', fn ($q) => $q->where(function ($q): void {
                    $term = '%'.trim($this->search).'%';
                    $q->where('ref', 'ilike', $term)->orWhere('title_en', 'ilike', $term)->orWhere('title_ar', 'ilike', $term)->orWhere('community', 'ilike', $term);
                }))
                ->orderByDesc('featured')->orderByDesc('id')
                ->paginate(20);
        });

        return view('livewire.listings.index', [
            'listings' => $listings,
            'used' => PlanLimits::listingsUsed($tenant),
            'limit' => PlanLimits::limit($tenant->account, 'listings'),
            'demoCount' => TenantContext::with($tenant, static fn (): int => Listing::query()->where('source', Listing::SOURCE_DEMO)->count()),
            'siteUrl' => Hosts::browserUrl($tenant->primaryHost(), '/'.app()->getLocale().'/listings'),
        ])->title(__('platform.listings.title'));
    }

    /** @param  callable(Listing): void  $change */
    private function update(int $id, callable $change): void
    {
        TenantContext::with($this->tenant(), static function () use ($id, $change): void {
            $listing = Listing::query()->whereKey($id)->real()->first();
            if ($listing !== null) {
                $change($listing);
                $listing->save();
            }
        });
    }
}
