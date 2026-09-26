<section class="section rule" id="services">
    <p class="eyebrow">{{ __('site.sections.services') }}</p>
    <dl class="mt-6 divide-y divide-line">
        @foreach (['buy', 'sell', 'rent'] as $service)
            <div class="grid gap-2 py-5 sm:grid-cols-3">
                <dt class="text-xl font-semibold">{{ __('site.services.'.$service.'.title') }}</dt>
                <dd class="text-muted sm:col-span-2">{{ __('site.services.'.$service.'.text') }}</dd>
            </div>
        @endforeach
    </dl>
</section>
