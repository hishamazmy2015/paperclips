<section class="section border-t border-line" id="services">
    <x-site.section-heading :title="__('site.sections.services')" />
    <div class="grid gap-4 md:grid-cols-3">
        @foreach (['buy', 'sell', 'rent'] as $i => $service)
            <div class="card p-6">
                <p class="eyebrow">0{{ $i + 1 }}</p>
                <p class="mt-2 text-lg font-semibold">{{ __('site.services.'.$service.'.title') }}</p>
                <p class="mt-2 text-muted">{{ __('site.services.'.$service.'.text') }}</p>
            </div>
        @endforeach
    </div>
</section>
