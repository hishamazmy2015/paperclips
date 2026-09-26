@if ($featured->isNotEmpty())
<section class="section" id="featured">
    <div class="flex items-end justify-between gap-4">
        <x-site.section-heading :eyebrow="__('site.nav.listings')" :title="__('site.sections.featured')" />
        <a href="{{ $site->url('listings') }}" class="mb-8 text-sm font-semibold text-primary">{{ __('site.cta.all_listings') }} →</a>
    </div>
    <div class="row-list">
        @foreach ($featured as $listing)
            <x-site.listing-card :site="$site" :listing="$listing" variant="row" :eager="$loop->first" />
        @endforeach
    </div>
</section>
@endif
