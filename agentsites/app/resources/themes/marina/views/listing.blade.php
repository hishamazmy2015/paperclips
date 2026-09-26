@php($title = $listing->title($site->locale))
@php($enquiry = __('site.whatsapp_listing', ['title' => $title, 'ref' => $listing->ref]))
<x-site.layout :site="$site" :title="$title" :description="\Illuminate\Support\Str::limit($listing->description($site->locale), 160, '')" :canonical-path="'listings/'.$listing->ref" :json-ld="$listing->isDemo() ? null : $site->offerJsonLd($listing)" :og-image="$listing->coverImage()">
    <article class="section">
        @if ($listing->images() !== [])
            <div class="scroll-row">
                @foreach ($listing->images() as $i => $image)
                    <img src="{{ $image }}" alt="{{ $title }}" class="aspect-[4/3] w-full rounded-xl object-cover" @if ($i > 0) loading="lazy" @else fetchpriority="high" @endif width="1200" height="900">
                @endforeach
            </div>
        @endif
        <div class="mt-8 grid gap-8 md:grid-cols-3">
            <div class="md:col-span-2">
                <p class="eyebrow">{{ $listing->offering === 'rent' ? __('site.listing.for_rent') : __('site.listing.for_sale') }} · {{ __('site.listing.type.'.$listing->property_type, [], null) }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $title }}</h1>
                <p class="mt-1 text-muted">{{ $listing->community }}@if ($listing->city), {{ $listing->city }}@endif · {{ __('site.listing.ref') }} <span dir="ltr">{{ $listing->ref }}</span></p>
                @if ($listing->isDemo())
                    <p class="mt-3 inline-block rounded-lg bg-line px-3 py-1 text-xs font-semibold">{{ __('site.listing.demo') }}</p>
                @endif
                <dl class="mt-6 flex flex-wrap gap-6 border-y border-line py-4">
                    <div><dt class="text-xs text-muted">{{ __('site.listing.details') }}</dt><dd class="font-semibold">{{ (int) $listing->bedrooms === 0 ? __('site.listing.studio') : trans_choice('site.listing.beds', (int) $listing->bedrooms) }}</dd></div>
                    @if ($listing->bathrooms)<div><dt class="text-xs text-muted">&nbsp;</dt><dd class="font-semibold">{{ trans_choice('site.listing.baths', (int) $listing->bathrooms) }}</dd></div>@endif
                    @if ($listing->area_sqft)<div><dt class="text-xs text-muted">&nbsp;</dt><dd class="font-semibold">{{ __('site.listing.sqft', ['area' => number_format((float) $listing->area_sqft)]) }}</dd></div>@endif
                </dl>
                <div class="prose-site mt-6 max-w-none"><p>{{ $listing->description($site->locale) }}</p></div>
            </div>
            <aside class="card h-fit p-6 md:sticky md:top-24">
                <p class="text-3xl font-bold text-primary">{{ $site->price($listing->price, $listing->currency) }}</p>
                @if ($listing->offering === 'rent')<p class="text-sm text-muted">{{ __('site.listing.per_year') }}</p>@endif
                <a href="{{ $site->whatsappUrl($enquiry) }}" rel="noopener" target="_blank" class="btn-primary mt-4 w-full">{{ __('site.cta.enquire') }}</a>
                <a href="tel:{{ $site->phone() }}" class="btn-outline mt-2 w-full">{{ __('site.cta.call') }}</a>
                <p class="mt-4 text-sm text-muted">{{ $site->name }}@if ($site->agency() !== '') · {{ $site->agency() }}@endif</p>
            </aside>
        </div>
        @if ($related->isNotEmpty())
            <section class="mt-14 border-t border-line pt-10">
                <x-site.section-heading :title="__('site.listing.related', ['area' => $listing->community])" />
                <div class="row-list">
                    @foreach ($related as $item)
                        <x-site.listing-card :site="$site" :listing="$item" variant="row" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
    <x-site.whatsapp-bar :site="$site" :text="$enquiry" />
</x-site.layout>
