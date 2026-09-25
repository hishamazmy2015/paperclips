<section class="section" id="services">
    <x-site.section-heading :title="__('site.sections.services')" />
    <div class="grid gap-6 md:grid-cols-3">
        @foreach (['buy', 'sell', 'rent'] as $service)
            <div class="card p-6">
                <p class="text-lg font-semibold">{{ __('site.services.'.$service.'.title') }}</p>
                <p class="mt-2 text-muted">{{ __('site.services.'.$service.'.text') }}</p>
            </div>
        @endforeach
    </div>
</section>
