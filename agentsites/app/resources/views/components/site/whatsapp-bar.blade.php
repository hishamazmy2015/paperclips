@props(['site', 'text' => null])
{{-- Sticky WhatsApp bar on phones: the primary conversion path (spec §2, §10 atlas). --}}
<div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 p-3 backdrop-blur md:hidden">
    <div class="mx-auto flex max-w-6xl gap-2">
        <a href="{{ $site->whatsappUrl($text) }}" rel="noopener" target="_blank" class="btn-primary flex-1">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.9 11.9 0 0 0 4.5 4c1.7.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.5-.3z"/></svg>
            {{ __('site.cta.whatsapp') }}
        </a>
        @if ($site->phone() !== '')
            <a href="tel:{{ $site->phone() }}" class="btn-outline">{{ __('site.cta.call') }}</a>
        @endif
    </div>
</div>
