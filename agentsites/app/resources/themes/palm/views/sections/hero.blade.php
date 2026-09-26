<section class="section text-center" id="hero">
    @if (($site->config['identity']['photo'] ?? '') !== '')
        <img src="{{ $site->config['identity']['photo'] }}" alt="{{ $site->name }}" class="mx-auto size-28 rounded-full object-cover" fetchpriority="high" width="112" height="112">
    @endif
    <p class="eyebrow mt-6">{{ $site->agency() !== '' ? $site->agency() : __('site.nav.home') }}</p>
    <h1 class="display mt-3">{{ $site->name }}</h1>
    <p class="lede mx-auto mt-6 max-w-2xl">{{ $site->tagline() }}</p>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
        <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
        <a href="{{ $site->url('listings') }}" class="gold-link self-center">{{ __('site.cta.view_listings') }}</a>
    </div>
    @if ($site->areas !== [])
        <p class="mt-8 text-sm text-muted">
            @foreach ($site->areas as $area)<a href="{{ $site->areaUrl($area) }}" class="hover:text-accent">{{ $area }}</a>@if (! $loop->last) <span aria-hidden="true" class="text-accent"> · </span>@endif @endforeach
        </p>
    @endif
</section>
