@php($hero = $site->config['branding']['hero_image'] ?? '')
<section class="relative isolate overflow-hidden bg-secondary text-white">
    @if ($hero !== '')
        <img src="{{ $hero }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-60" fetchpriority="high" width="1600" height="900">
    @else
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-secondary via-secondary to-primary"></div>
    @endif
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-20 sm:px-6 lg:py-28">
        <div class="flex items-center gap-4">
            @if (($site->config['identity']['photo'] ?? '') !== '')
                <img src="{{ $site->config['identity']['photo'] }}" alt="{{ $site->name }}" class="size-20 rounded-full border-2 border-white/60 object-cover" width="80" height="80">
            @endif
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-white/80">{{ $site->agency() !== '' ? $site->agency() : __('site.nav.home') }}</p>
                <h1 class="text-3xl font-bold sm:text-5xl">{{ $site->name }}</h1>
            </div>
        </div>
        <p class="max-w-2xl text-lg text-white/90 sm:text-2xl">{{ $site->tagline() }}</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary">{{ __('site.cta.enquire') }}</a>
            <a href="{{ $site->url('listings') }}" class="btn border border-white/60 text-white hover:bg-white/10">{{ __('site.cta.view_listings') }}</a>
        </div>
        @if ($site->areas !== [])
            <ul class="flex flex-wrap gap-2 text-sm">
                @foreach ($site->areas as $area)
                    <li><a href="{{ $site->areaUrl($area) }}" class="rounded-full border border-white/40 px-3 py-1 hover:bg-white/10">{{ $area }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
