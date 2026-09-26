@if ($site->areas !== [])
<section class="section border-t border-line" id="areas">
    <x-site.section-heading :eyebrow="__('site.nav.areas')" :title="__('site.sections.areas')" />
    <ul class="flex flex-wrap gap-3">
        @foreach ($site->areas as $area)
            <li><a href="{{ $site->areaUrl($area) }}" class="btn-outline">{{ $area }} <span aria-hidden="true" class="text-primary">→</span></a></li>
        @endforeach
    </ul>
</section>
@endif
