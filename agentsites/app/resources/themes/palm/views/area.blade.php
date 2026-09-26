<x-site.layout :site="$site" :title="__('site.area.title', ['area' => $area])" :description="__('site.area.intro', ['area' => $area, 'name' => $site->name])" :canonical-path="'areas/'.\Illuminate\Support\Str::slug($area)">
    <section class="section">
        <p class="eyebrow">{{ __('site.nav.areas') }}</p>
        <h1 class="display mt-3">{{ $area }}</h1>
        <p class="lede mt-6">{{ __('site.area.intro', ['area' => $area, 'name' => $site->name]) }}</p>
        @if ($listings->isEmpty())
            <p class="mt-10 text-muted">{{ __('site.listing.no_results') }}</p>
        @else
            <div class="mt-12 grid gap-12 sm:grid-cols-2">
                @foreach ($listings as $listing)
                    <x-site.listing-card :site="$site" :listing="$listing" variant="editorial" />
                @endforeach
            </div>
        @endif
        <a href="{{ $site->whatsappUrl(__('site.whatsapp_default', ['name' => $site->name]).' ('.$area.')') }}" rel="noopener" target="_blank" class="btn-primary mt-12">{{ __('site.cta.enquire') }}</a>
    </section>
</x-site.layout>
