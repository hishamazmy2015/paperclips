@php($title = $listing->title($site->locale))
@php($enquiry = __('site.whatsapp_listing', ['title' => $title, 'ref' => $listing->ref]))
<x-site.layout :site="$site" :title="$title" :description="\Illuminate\Support\Str::limit($listing->description($site->locale), 160, '')" :canonical-path="'listings/'.$listing->ref" :json-ld="$listing->isDemo() ? null : $site->offerJsonLd($listing)" :og-image="$listing->coverImage()">
    <article class="section">
        <p class="eyebrow">{{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }} · {{ __('site.listing.type.'.$listing->property_type, [], null) }}@if ($listing->isDemo()) · {{ __('site.listing.demo') }}@endif</p>
        <h1 class="h2 mt-3">{{ $title }}</h1>
        <p class="mt-2 text-muted">{{ $listing->community }}@if ($listing->city), {{ $listing->city }}@endif · {{ __('site.listing.ref') }} <span dir="ltr">{{ $listing->ref }}</span></p>
        <p class="mt-6 text-4xl font-bold tracking-tighter text-accent">{{ $site->price($listing->price, $listing->currency) }}@if ($listing->offering === 'rent') <span class="text-base font-normal text-muted">{{ __('site.listing.per_year') }}</span>@endif</p>
        @if ($listing->images() !== [])
            <div class="mt-8 space-y-4">
                @foreach ($listing->images() as $i => $image)
                    <img src="{{ $image }}" alt="{{ $title }}" class="aspect-[3/2] w-full object-cover" @if ($i > 0) loading="lazy" @else fetchpriority="high" @endif width="1200" height="800">
                @endforeach
            </div>
        @endif
        <dl class="mt-8 flex flex-wrap gap-8 border-y border-line py-5">
            <div><dt class="text-xs uppercase tracking-widest text-muted">{{ __('site.listing.details') }}</dt><dd class="mt-1 text-lg font-semibold">{{ (int) $listing->bedrooms === 0 ? __('site.listing.studio') : trans_choice('site.listing.beds', (int) $listing->bedrooms) }}</dd></div>
            @if ($listing->bathrooms)<div><dt class="text-xs uppercase tracking-widest text-muted">&nbsp;</dt><dd class="mt-1 text-lg font-semibold">{{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}</dd></div>@endif
            @if ($listing->area_sqft)<div><dt class="text-xs uppercase tracking-widest text-muted">&nbsp;</dt><dd class="mt-1 text-lg font-semibold">{{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}</dd></div>@endif
        </dl>
        <div class="prose-site mt-8"><p>{{ $listing->description($site->locale) }}</p></div>
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="{{ $site->whatsappUrl($enquiry) }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
            <a href="tel:{{ $site->phone() }}" class="btn-outline">{{ __('site.cta.call') }}</a>
        </div>
        @if ($related->isNotEmpty())
            <section class="mt-16 rule pt-10">
                <h2 class="h2">{{ __('site.listing.related', ['area' => $listing->community]) }}</h2>
                <div class="mt-8 grid gap-12 sm:grid-cols-2">
                    @foreach ($related as $item)
                        <x-site.listing-card :site="$site" :listing="$item" variant="editorial" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
    <x-site.whatsapp-bar :site="$site" :text="$enquiry" />
</x-site.layout>
