@if ($featured->isNotEmpty())
<section class="section rule" id="featured">
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="h2">{{ __('site.sections.featured') }}</h2>
        <a href="{{ $site->url('listings') }}" class="gold-link text-sm">{{ __('site.cta.all_listings') }}</a>
    </div>
    <div class="mt-10 grid gap-12 sm:grid-cols-2">
        @foreach ($featured->take(4) as $listing)
            <x-site.listing-card :site="$site" :listing="$listing" variant="editorial" :eager="$loop->first" />
        @endforeach
    </div>
</section>
@endif
