@if ($site->areas !== [])
<section class="section rule" id="areas">
    <p class="eyebrow">{{ __('site.nav.areas') }}</p>
    <h2 class="h2 mt-3">{{ __('site.sections.areas') }}</h2>
    <ul class="mt-6 divide-y divide-line">
        @foreach ($site->areas as $area)
            <li><a href="{{ $site->areaUrl($area) }}" class="flex items-center justify-between py-4 text-xl font-medium hover:text-accent">{{ $area }} <span aria-hidden="true" class="text-accent">→</span></a></li>
        @endforeach
    </ul>
</section>
@endif
