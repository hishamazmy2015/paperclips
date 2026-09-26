<x-site.layout :site="$site" :title="__('site.nav.listings')" canonical-path="listings">
    <section class="section">
        <x-site.section-heading level="1" :title="__('site.nav.listings')" />

        <form method="get" class="card mb-8 grid gap-3 p-4 sm:grid-cols-4" aria-label="{{ __('site.listing.filters') }}">
            <select name="offering" class="rounded-xl border border-line bg-surface px-3 py-2">
                <option value="">{{ __('site.listing.any') }}</option>
                <option value="sale" @selected($filters['offering'] === 'sale')>{{ __('site.listing.for_sale') }}</option>
                <option value="rent" @selected($filters['offering'] === 'rent')>{{ __('site.listing.for_rent') }}</option>
            </select>
            <select name="type" class="rounded-xl border border-line bg-surface px-3 py-2">
                <option value="">{{ __('site.listing.any') }}</option>
                @foreach (['apartment', 'villa', 'townhouse', 'penthouse', 'studio'] as $type)
                    <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __('site.listing.type.'.$type) }}</option>
                @endforeach
            </select>
            <select name="beds" class="rounded-xl border border-line bg-surface px-3 py-2">
                <option value="">{{ __('site.listing.any') }}</option>
                @foreach ([1, 2, 3, 4] as $n)
                    <option value="{{ $n }}" @selected($filters['beds'] === $n)>{{ trans_choice('site.listing.beds', $n) }}+</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary">{{ __('site.listing.filters') }}</button>
        </form>

        @if ($listings->isEmpty())
            <p class="text-muted">{{ __('site.listing.no_results') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-site.listing-card :site="$site" :listing="$listing" />
                @endforeach
            </div>
            <div class="mt-8">{{ $listings->links() }}</div>
        @endif
    </section>
</x-site.layout>
