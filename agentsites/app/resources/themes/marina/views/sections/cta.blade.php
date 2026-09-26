<section class="section" id="cta">
    <div class="flex flex-col items-start gap-4 rounded-xl bg-primary p-8 text-primary-contrast md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-2xl font-bold">{{ __('site.sections.cta') }}</h2>
            <p class="mt-1 opacity-90">{{ __('site.sections.cta_text') }}</p>
        </div>
        <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn bg-surface text-ink hover:opacity-90">{{ __('site.cta.enquire') }}</a>
    </div>
</section>
