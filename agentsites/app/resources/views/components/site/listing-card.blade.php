@props(['site', 'listing', 'variant' => 'grid', 'eager' => false])
@php($title = $listing->title($site->locale))
@php($beds = (int) $listing->bedrooms === 0 ? __('site.listing.studio') : trans_choice('site.listing.beds', (int) $listing->bedrooms))
@if ($variant === 'row')
    {{-- marina: image on the side, details beside it --}}
    <article class="card flex min-w-0 overflow-hidden">
        <a href="{{ $site->url('listings/'.$listing->ref) }}" class="flex w-full">
            <div class="relative w-2/5 shrink-0 bg-line">
                @if ($listing->coverImage())
                    <img src="{{ $listing->coverImage() }}" alt="{{ $title }}" class="size-full object-cover" loading="{{ $eager ? 'eager' : 'lazy' }}"@if ($eager) fetchpriority="high"@endif width="600" height="600">
                @endif
                @if ($listing->isDemo())
                    <span class="absolute start-2 top-2 rounded-md bg-secondary/80 px-2 py-0.5 text-xs font-semibold text-white">{{ __('site.listing.demo') }}</span>
                @endif
            </div>
            <div class="flex flex-1 flex-col gap-1 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }}@if ($listing->featured) · {{ __('site.listing.featured') }}@endif</p>
                <h3 class="font-semibold leading-snug">{{ $title }}</h3>
                <p class="text-sm text-muted">{{ $listing->community }}@if ($listing->city), {{ $listing->city }}@endif</p>
                <p class="mt-auto text-lg font-bold text-primary">{{ $site->price($listing->price, $listing->currency) }}@if ($listing->offering === 'rent') <span class="text-sm font-normal text-muted">{{ __('site.listing.per_year') }}</span>@endif</p>
                <p class="flex flex-wrap gap-x-3 text-sm text-muted">
                    <span>{{ $beds }}</span>
                    @if ($listing->bathrooms)<span>{{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}</span>@endif
                    @if ($listing->area_sqft)<span>{{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}</span>@endif
                </p>
            </div>
        </a>
    </article>
@elseif ($variant === 'editorial')
    {{-- palm: a large image, big type, the price in the accent colour --}}
    <article class="min-w-0">
        <a href="{{ $site->url('listings/'.$listing->ref) }}" class="group block">
            <div class="relative aspect-[3/2] bg-line">
                @if ($listing->coverImage())
                    <img src="{{ $listing->coverImage() }}" alt="{{ $title }}" class="size-full object-cover" loading="{{ $eager ? 'eager' : 'lazy' }}"@if ($eager) fetchpriority="high"@endif width="900" height="600">
                @endif
                @if ($listing->isDemo())
                    <span class="absolute start-3 top-3 bg-page px-2 py-0.5 text-xs font-semibold">{{ __('site.listing.demo') }}</span>
                @endif
            </div>
            <p class="eyebrow mt-4">{{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }} · {{ $listing->community }}</p>
            <h3 class="mt-2 text-2xl font-bold leading-tight tracking-tight group-hover:text-accent">{{ $title }}</h3>
            <p class="mt-2 text-xl font-bold text-accent">{{ $site->price($listing->price, $listing->currency) }}@if ($listing->offering === 'rent') <span class="text-sm font-normal text-muted">{{ __('site.listing.per_year') }}</span>@endif</p>
            <p class="mt-1 text-sm text-muted">{{ $beds }}@if ($listing->bathrooms) · {{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}@endif @if ($listing->area_sqft) · {{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}@endif</p>
        </a>
    </article>
@else
    {{-- atlas: grid card --}}
    <article class="card min-w-0 overflow-hidden">
        <a href="{{ $site->url('listings/'.$listing->ref) }}" class="block">
            <div class="relative aspect-[4/3] bg-line">
                @if ($listing->coverImage())
                    <img src="{{ $listing->coverImage() }}" alt="{{ $title }}" class="size-full object-cover" loading="{{ $eager ? 'eager' : 'lazy' }}"@if ($eager) fetchpriority="high"@endif width="800" height="600">
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
                <h3 class="font-semibold leading-snug">{{ $title }}</h3>
                <p class="text-sm text-muted">{{ $listing->community }}@if ($listing->city), {{ $listing->city }}@endif</p>
                <p class="flex flex-wrap gap-x-3 text-sm text-muted">
                    <span>{{ $beds }}</span>
                    @if ($listing->bathrooms)<span>{{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}</span>@endif
                    @if ($listing->area_sqft)<span>{{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}</span>@endif
                </p>
            </div>
        </a>
    </article>
@endif
