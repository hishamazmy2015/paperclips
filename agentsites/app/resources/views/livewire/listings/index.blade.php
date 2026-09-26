<div class="app-card" data-test="listings-index">
    <x-app-nav active="listings" />
    <div class="flex items-baseline justify-between gap-3">
        <h1 class="app-h1">{{ __('platform.listings.title') }}</h1>
        <span class="app-fineprint" data-test="plan-usage">{{ __('platform.listings.usage', ['used' => $used, 'limit' => $limit ?? '∞']) }}</span>
    </div>
    @if (session('status'))
        <p class="app-ok-text" role="status" data-test="flash">{{ session('status') }}</p>
    @endif
    <div class="app-actions-row">
        <a href="{{ route('listings.new') }}" class="btn-primary" data-test="new-listing">{{ __('platform.listings.new') }}</a>
        <a href="{{ $siteUrl }}" class="app-link" target="_blank" rel="noopener">{{ __('platform.listings.view_on_site') }}</a>
    </div>
    <input type="search" class="app-input" placeholder="{{ __('platform.listings.search') }}" wire:model.live.debounce.300ms="search" aria-label="{{ __('platform.listings.search') }}" data-test="search">

    @if ($listings->isEmpty())
        <p class="app-lead" data-test="empty">{{ $demoCount > 0 ? __('platform.listings.empty_demo', ['count' => $demoCount]) : __('platform.listings.empty') }}</p>
    @else
        <ul class="listing-rows" data-test="rows">
            @foreach ($listings as $listing)
                <li class="listing-row {{ $listing->status === 'hidden' ? 'is-hidden' : '' }}" wire:key="listing-{{ $listing->id }}" data-test="row-{{ $listing->ref }}">
                    <a href="{{ route('listings.edit', $listing) }}" class="listing-row-main">
                        @if ($listing->coverImage())
                            <img src="{{ $listing->imageSets()[0]['thumb'] }}" alt="" class="listing-thumb" width="56" height="56">
                        @else
                            <span class="listing-thumb listing-thumb-empty" aria-hidden="true"></span>
                        @endif
                        <span class="listing-row-text">
                            <strong>{{ $listing->title_en }}</strong>
                            <small dir="ltr">{{ $listing->ref }} · {{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }} · {{ number_format((float) $listing->price) }} {{ $listing->currency }}</small>
                            <small>{{ $listing->community }} · {{ __('platform.listings.status_'.$listing->status) }}@if ($listing->source !== 'manual') · {{ __('platform.listings.source_'.$listing->source) }}@endif</small>
                        </span>
                    </a>
                    <span class="listing-row-actions">
                        <button type="button" class="app-chip {{ $listing->featured ? 'is-selected' : '' }}" wire:click="toggleFeatured({{ $listing->id }})" aria-pressed="{{ $listing->featured ? 'true' : 'false' }}" data-test="feature-{{ $listing->ref }}">★</button>
                        <button type="button" class="app-chip" wire:click="toggleHidden({{ $listing->id }})" data-test="hide-{{ $listing->ref }}">{{ $listing->status === 'hidden' ? __('platform.listings.show') : __('platform.listings.hide') }}</button>
                        <button type="button" class="app-chip" wire:click="delete({{ $listing->id }})" wire:confirm="{{ __('platform.listings.delete_confirm') }}" data-test="delete-{{ $listing->ref }}">{{ __('platform.listings.delete') }}</button>
                    </span>
                </li>
            @endforeach
        </ul>
        {{ $listings->links() }}
    @endif
</div>
