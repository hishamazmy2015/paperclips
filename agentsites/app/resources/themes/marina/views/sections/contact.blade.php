<section class="section border-t border-line" id="contact">
    <x-site.section-heading :title="__('site.sections.contact')" />
    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="card p-5 hover:border-primary">
            <p class="font-semibold">{{ __('site.cta.whatsapp') }}</p>
            <p class="mt-1 text-muted" dir="ltr">{{ $site->whatsapp() }}</p>
        </a>
        @if ($site->phone() !== '')
            <a href="tel:{{ $site->phone() }}" class="card p-5 hover:border-primary">
                <p class="font-semibold">{{ __('site.contact.phone') }}</p>
                <p class="mt-1 text-muted" dir="ltr">{{ $site->phone() }}</p>
            </a>
        @endif
        @if ($site->email() !== '')
            <a href="mailto:{{ $site->email() }}" class="card p-5 hover:border-primary">
                <p class="font-semibold">{{ __('site.contact.email') }}</p>
                <p class="mt-1 text-muted">{{ $site->email() }}</p>
            </a>
        @endif
        @if (($site->config['contact']['office_address'] ?? '') !== '')
            <div class="card p-5">
                <p class="font-semibold">{{ __('site.contact.office') }}</p>
                <p class="mt-1 text-muted">{{ $site->config['contact']['office_address'] }}</p>
            </div>
        @endif
    </div>
</section>
