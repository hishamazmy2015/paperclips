@php($lat = $site->config['contact']['lat'] ?? null)
@php($lng = $site->config['contact']['lng'] ?? null)
@if ($lat !== null && $lng !== null)
<section class="section rule" id="map">
    <p class="eyebrow">{{ __('site.sections.map') }}</p>
    <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=16/{{ $lat }}/{{ $lng }}" rel="noopener" target="_blank" class="gold-link mt-4 inline-block">{{ $site->config['contact']['office_address'] ?? __('site.contact.office') }} <span dir="ltr">({{ $lat }}, {{ $lng }})</span></a>
</section>
@endif
