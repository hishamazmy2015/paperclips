@if ($site->areas !== [])
<section class="section" id="areas">
    <x-site.section-heading :title="__('site.sections.areas')" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($site->areas as $area)
            <a href="{{ $site->areaUrl($area) }}" class="card flex items-center justify-between p-5 hover:border-primary">
                <span class="font-semibold">{{ $area }}</span>
                <span aria-hidden="true" class="text-primary">→</span>
            </a>
        @endforeach
    </div>
</section>
@endif
