@php($photo = $site->config['branding']['hero_image'] ?: ($site->config['identity']['photo'] ?? ''))
<section class="section" id="hero">
    <div class="split-hero">
        @if ($photo !== '')
            <img src="{{ $photo }}" alt="{{ $site->name }}" class="split-hero-photo" fetchpriority="high" width="800" height="1000">
        @else
            <div class="split-hero-photo grid place-items-center bg-gradient-to-br from-primary to-secondary text-6xl font-bold text-primary-contrast" aria-hidden="true">{{ mb_substr($site->name, 0, 1) }}</div>
        @endif
        <div class="space-y-5">
            <p class="eyebrow">{{ $site->agency() !== '' ? $site->agency() : __('site.nav.home') }}</p>
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $site->name }}</h1>
            <p class="text-lg text-muted sm:text-xl">{{ $site->tagline() }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
                <a href="{{ $site->url('listings') }}" class="btn-outline">{{ __('site.cta.view_listings') }}</a>
            </div>
            @if ($site->areas !== [])
                <ul class="flex flex-wrap gap-2 text-sm">
                    @foreach ($site->areas as $area)
                        <li><a href="{{ $site->areaUrl($area) }}" class="rounded-lg border border-line px-3 py-1 hover:border-primary">{{ $area }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
