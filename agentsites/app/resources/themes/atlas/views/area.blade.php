<x-site.layout :site="$site" :title="__('site.area.title', ['area' => $area])" :description="__('site.area.intro', ['area' => $area, 'name' => $site->name])" :canonical-path="'areas/'.\Illuminate\Support\Str::slug($area)">
    <section class="section">
        <x-site.section-heading :eyebrow="__('site.nav.areas')" :title="__('site.area.title', ['area' => $area])" />
        <p class="mb-8 max-w-2xl text-lg text-muted">{{ __('site.area.intro', ['area' => $area, 'name' => $site->name]) }}</p>
        @if ($listings->isEmpty())
            <p class="text-muted">{{ __('site.listing.no_results') }}</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-site.listing-card :site="$site" :listing="$listing" />
                @endforeach
            </div>
        @endif
        <div class="mt-10">
            <a href="{{ $site->whatsappUrl(__('site.whatsapp_default', ['name' => $site->name]).' ('.$area.')') }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
        </div>
    </section>
</x-site.layout>
