<section class="section" id="cta">
    <div class="card flex flex-col items-start gap-4 p-8 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="h2">{{ __('site.sections.cta') }}</h2>
            <p class="mt-1 text-muted">{{ __('site.sections.cta_text') }}</p>
        </div>
        <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
    </div>
</section>
