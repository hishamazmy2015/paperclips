@props(['site', 'listing'])
<article class="card overflow-hidden">
    <a href="{{ $site->url('listings/'.$listing->ref) }}" class="block">
        <div class="relative aspect-[4/3] bg-line">
            @if ($listing->coverImage())
                <img src="{{ $listing->coverImage() }}" alt="{{ $listing->title($site->locale) }}" class="size-full object-cover" loading="lazy" width="800" height="600">
            @endif
            <span class="absolute start-3 top-3 rounded-full bg-surface/90 px-3 py-1 text-xs font-semibold">
                {{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }}
            </span>
            @if ($listing->isDemo())
                <span class="absolute end-3 top-3 rounded-full bg-secondary/80 px-3 py-1 text-xs font-semibold text-white">{{ __('site.listing.demo') }}</span>
            @elseif ($listing->featured)
                <span class="absolute end-3 top-3 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white">{{ __('site.listing.featured') }}</span>
            @endif
        </div>
        <div class="space-y-2 p-4">
            <p class="text-lg font-bold text-primary">{{ $site->price($listing->price, $listing->currency) }}@if ($listing->offering === 'rent') <span class="text-sm font-normal text-muted">{{ __('site.listing.per_year') }}</span>@endif</p>
            <h3 class="font-semibold leading-snug">{{ $listing->title($site->locale) }}</h3>
            <p class="text-sm text-muted">{{ $listing->community }}@if ($listing->city), {{ $listing->city }}@endif</p>
            <p class="flex flex-wrap gap-x-3 text-sm text-muted">
                <span>{{ (int) $listing->bedrooms === 0 ? __('site.listing.studio') : trans_choice('site.listing.beds', (int) $listing->bedrooms) }}</span>
                @if ($listing->bathrooms)<span>{{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}</span>@endif
                @if ($listing->area_sqft)<span>{{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}</span>@endif
            </p>
        </div>
    </a>
</article>
