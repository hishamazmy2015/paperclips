<x-site.layout :site="$site" :title="__('site.nav.about')" canonical-path="about">
    <section class="section">
        @if (($site->config['identity']['photo'] ?? '') !== '')
            <img src="{{ $site->config['identity']['photo'] }}" alt="{{ $site->name }}" class="size-32 rounded-full object-cover" width="128" height="128">
        @endif
        <p class="eyebrow mt-6">{{ $site->agency() !== '' ? $site->agency() : __('site.nav.about') }}</p>
        <h1 class="display mt-3">{{ __('site.sections.about', ['name' => $site->name]) }}</h1>
        <div class="prose-site mt-8"><p>{{ $site->bio() }}</p><p>{{ $site->about() }}</p></div>
        <p class="text-sm text-muted">
            @if (($site->config['identity']['years_experience'] ?? 0) > 0){{ $site->config['identity']['years_experience'] }}+ {{ __('site.stats.years') }} · @endif
            @if (($site->config['identity']['languages'] ?? []) !== []){{ __('site.stats.languages') }}: {{ implode(', ', array_map('strtoupper', (array) $site->config['identity']['languages'])) }}@endif
        </p>
        @if ($site->whyMe() !== [])
            <h2 class="h2 mt-12">{{ __('site.sections.why_me') }}</h2>
            <dl class="mt-6 grid gap-6 sm:grid-cols-3">
                @foreach ($site->whyMe() as $item)
                    <div class="card"><dt class="font-semibold">{{ $item['title'] }}</dt><dd class="mt-1 text-sm text-muted">{{ $item['text'] }}</dd></div>
                @endforeach
            </dl>
        @endif
        <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary mt-10">{{ __('site.cta.whatsapp') }}</a>
    </section>
    @if ($testimonials->isNotEmpty())
        @include('theme-palm::sections.testimonials', ['site' => $site, 'testimonials' => $testimonials, 'forward' => true])
    @endif
</x-site.layout>
