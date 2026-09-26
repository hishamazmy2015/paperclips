<section class="section rule" id="contact">
    <p class="eyebrow">{{ __('site.sections.contact') }}</p>
    <dl class="mt-6 divide-y divide-line">
        <div class="flex items-baseline justify-between gap-4 py-4"><dt class="font-semibold">{{ __('site.cta.whatsapp') }}</dt><dd><a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="gold-link" dir="ltr">{{ $site->whatsapp() }}</a></dd></div>
        @if ($site->phone() !== '')
            <div class="flex items-baseline justify-between gap-4 py-4"><dt class="font-semibold">{{ __('site.contact.phone') }}</dt><dd><a href="tel:{{ $site->phone() }}" class="gold-link" dir="ltr">{{ $site->phone() }}</a></dd></div>
        @endif
        @if ($site->email() !== '')
            <div class="flex items-baseline justify-between gap-4 py-4"><dt class="font-semibold">{{ __('site.contact.email') }}</dt><dd><a href="mailto:{{ $site->email() }}" class="gold-link">{{ $site->email() }}</a></dd></div>
        @endif
        @if (($site->config['contact']['office_address'] ?? '') !== '')
            <div class="flex items-baseline justify-between gap-4 py-4"><dt class="font-semibold">{{ __('site.contact.office') }}</dt><dd class="text-muted">{{ $site->config['contact']['office_address'] }}</dd></div>
        @endif
    </dl>
</section>
