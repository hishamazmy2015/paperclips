<x-site.layout :site="$site" :title="__('site.nav.about')" canonical-path="about">
    <section class="section">
        <div class="split-hero">
            @if (($site->config['identity']['photo'] ?? '') !== '')
                <img src="{{ $site->config['identity']['photo'] }}" alt="{{ $site->name }}" class="split-hero-photo" width="800" height="1000">
            @endif
            <div>
                <x-site.section-heading level="1" :eyebrow="$site->agency()" :title="__('site.sections.about', ['name' => $site->name])" />
                <div class="prose-site max-w-none"><p>{{ $site->bio() }}</p><p>{{ $site->about() }}</p></div>
                @if (($site->config['identity']['years_experience'] ?? 0) > 0)
                    <p class="text-sm text-muted">{{ $site->config['identity']['years_experience'] }}+ {{ __('site.stats.years') }}</p>
                @endif
                @if (($site->config['identity']['languages'] ?? []) !== [])
                    <p class="text-sm text-muted">{{ __('site.stats.languages') }}: {{ implode(', ', array_map('strtoupper', (array) $site->config['identity']['languages'])) }}</p>
                @endif
                <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary mt-4">{{ __('site.cta.whatsapp') }}</a>
            </div>
        </div>
        @if ($site->whyMe() !== [])
            <h2 class="h2 mt-12">{{ __('site.sections.why_me') }}</h2>
            <ol class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($site->whyMe() as $i => $item)
                    <li class="card p-5"><p class="eyebrow">0{{ $i + 1 }}</p><p class="mt-2 font-semibold">{{ $item['title'] }}</p><p class="mt-1 text-sm text-muted">{{ $item['text'] }}</p></li>
                @endforeach
            </ol>
        @endif
        @if ($testimonials->isNotEmpty())
            @include('theme-marina::sections.testimonials', ['site' => $site, 'testimonials' => $testimonials])
        @endif
    </section>
</x-site.layout>
