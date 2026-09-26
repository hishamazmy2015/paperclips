<x-site.layout :site="$site" :title="__('site.nav.listings')" canonical-path="listings">
    <section class="section">
        <p class="eyebrow">{{ $site->name }}</p>
        <h1 class="h2 mt-3">{{ __('site.nav.listings') }}</h1>
        <form method="get" class="mt-8 grid gap-3 border-y border-line py-4 sm:grid-cols-4" aria-label="{{ __('site.listing.filters') }}">
            <select name="offering" class="border-b border-ink bg-transparent px-1 py-2" aria-label="{{ __('site.listing.for_sale') }} / {{ __('site.listing.for_rent') }}">
                <option value="">{{ __('site.listing.any') }}</option>
                <option value="sale" @selected($filters['offering'] === 'sale')>{{ __('site.listing.for_sale') }}</option>
                <option value="rent" @selected($filters['offering'] === 'rent')>{{ __('site.listing.for_rent') }}</option>
            </select>
            <select name="type" class="border-b border-ink bg-transparent px-1 py-2" aria-label="{{ __('site.listing.type.apartment') }}">
                <option value="">{{ __('site.listing.any') }}</option>
                @foreach (['apartment', 'villa', 'townhouse', 'penthouse', 'studio'] as $type)
                    <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __('site.listing.type.'.$type) }}</option>
                @endforeach
            </select>
            <select name="beds" class="border-b border-ink bg-transparent px-1 py-2" aria-label="{{ trans_choice('site.listing.beds', 2) }}">
                <option value="">{{ __('site.listing.any') }}</option>
                @foreach ([1, 2, 3, 4] as $n)
                    <option value="{{ $n }}" @selected($filters['beds'] === $n)>{{ trans_choice('site.listing.beds', $n) }}+</option>
                @endforeach
            </select>
            <button type="submit" class="btn-outline">{{ __('site.listing.filters') }}</button>
        </form>
        @if ($listings->isEmpty())
            <p class="mt-8 text-muted">{{ __('site.listing.no_results') }}</p>
        @else
            <div class="mt-10 grid gap-12 sm:grid-cols-2">
                @foreach ($listings as $listing)
                    <x-site.listing-card :site="$site" :listing="$listing" variant="editorial" />
                @endforeach
            </div>
            <div class="mt-10">{{ $listings->links() }}</div>
        @endif
    </section>
</x-site.layout>
