@php($lat = $site->config['contact']['lat'] ?? null)
@php($lng = $site->config['contact']['lng'] ?? null)
@if ($lat !== null && $lng !== null)
<section class="section" id="map">
    <x-site.section-heading :title="__('site.sections.map')" />
    {{-- No third-party script (spec §6): a static link to the pin; an embedded map is a Phase 3 option. --}}
    <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=16/{{ $lat }}/{{ $lng }}" rel="noopener" target="_blank" class="card block p-6 hover:border-primary">
        <p class="font-semibold">{{ $site->config['contact']['office_address'] ?? __('site.contact.office') }}</p>
        <p class="mt-1 text-sm text-muted" dir="ltr">{{ $lat }}, {{ $lng }}</p>
    </a>
</section>
@endif
