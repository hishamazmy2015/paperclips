@props(['site'])
@php($current = request()->getPathInfo())
<header class="sticky top-0 z-40 border-b border-line bg-surface/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ $site->url() }}" class="flex items-center gap-3 font-bold text-ink">
            @if (($site->config['identity']['logo'] ?? '') !== '')
                <img src="{{ $site->config['identity']['logo'] }}" alt="{{ $site->name }}" class="h-9 w-auto" width="36" height="36">
            @else
                <span class="grid size-9 place-items-center rounded-full bg-primary text-primary-contrast">{{ mb_substr($site->name, 0, 1) }}</span>
            @endif
            <span class="truncate">{{ $site->name }}</span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-medium md:flex" aria-label="{{ __('site.nav.menu') }}">
            <a href="{{ $site->url() }}" class="hover:text-primary">{{ __('site.nav.home') }}</a>
            <a href="{{ $site->url('listings') }}" class="hover:text-primary">{{ __('site.nav.listings') }}</a>
            <a href="{{ $site->url('about') }}" class="hover:text-primary">{{ __('site.nav.about') }}</a>
            <a href="{{ $site->url('contact') }}" class="hover:text-primary">{{ __('site.nav.contact') }}</a>
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ $site->switchLocaleUrl($current) }}" hreflang="{{ $site->otherLocale }}" lang="{{ $site->otherLocale }}" class="rounded-full border border-line px-3 py-1.5 text-sm font-semibold">{{ $site->otherLocale === 'ar' ? 'العربية' : 'English' }}</a>
            <a href="{{ $site->whatsappUrl() }}" class="btn-primary hidden !py-2 md:inline-flex" rel="noopener" target="_blank">{{ __('site.cta.whatsapp') }}</a>
            <button type="button" class="md:hidden rounded-full border border-line p-2" aria-controls="mobile-nav" aria-expanded="false" onclick="var n=document.getElementById('mobile-nav');var o=n.hasAttribute('hidden');if(o){n.removeAttribute('hidden')}else{n.setAttribute('hidden','')}this.setAttribute('aria-expanded',o?'true':'false')" aria-label="{{ __('site.nav.menu') }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>
    <nav id="mobile-nav" hidden class="border-t border-line bg-surface px-4 py-3 md:hidden" aria-label="{{ __('site.nav.menu') }}">
        <a href="{{ $site->url() }}" class="block py-2 font-medium">{{ __('site.nav.home') }}</a>
        <a href="{{ $site->url('listings') }}" class="block py-2 font-medium">{{ __('site.nav.listings') }}</a>
        <a href="{{ $site->url('about') }}" class="block py-2 font-medium">{{ __('site.nav.about') }}</a>
        <a href="{{ $site->url('contact') }}" class="block py-2 font-medium">{{ __('site.nav.contact') }}</a>
    </nav>
</header>
