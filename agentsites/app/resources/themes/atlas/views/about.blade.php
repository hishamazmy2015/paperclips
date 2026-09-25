<x-site.layout :site="$site" :title="__('site.nav.about')" canonical-path="about">
    <section class="section">
        <div class="grid gap-10 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-site.section-heading :eyebrow="$site->agency()" :title="__('site.sections.about', ['name' => $site->name])" />
                <div class="prose-site max-w-none">
                    <p>{{ $site->bio() }}</p>
                    <p>{{ $site->about() }}</p>
                </div>
                @if ($site->whyMe() !== [])
                    <h2 class="h2 mt-10">{{ __('site.sections.why_me') }}</h2>
                    <ul class="mt-6 grid gap-4 sm:grid-cols-3">
                        @foreach ($site->whyMe() as $item)
                            <li class="card p-5"><p class="font-semibold">{{ $item['title'] }}</p><p class="mt-1 text-sm text-muted">{{ $item['text'] }}</p></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <aside class="space-y-4">
                @if (($site->config['identity']['photo'] ?? '') !== '')
                    <img src="{{ $site->config['identity']['photo'] }}" alt="{{ $site->name }}" class="aspect-square w-full rounded-3xl object-cover" width="600" height="600">
                @endif
                <div class="card p-6">
                    <p class="font-semibold">{{ $site->name }}</p>
                    @if ($site->agency() !== '')<p class="text-muted">{{ $site->agency() }}</p>@endif
                    @if (($site->config['identity']['years_experience'] ?? 0) > 0)
                        <p class="mt-2 text-sm text-muted">{{ $site->config['identity']['years_experience'] }}+ {{ __('site.stats.years') }}</p>
                    @endif
                    @if (($site->config['identity']['languages'] ?? []) !== [])
                        <p class="text-sm text-muted">{{ __('site.stats.languages') }}: {{ implode(', ', array_map('strtoupper', (array) $site->config['identity']['languages'])) }}</p>
                    @endif
                    <a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="btn-primary mt-4 w-full">{{ __('site.cta.whatsapp') }}</a>
                </div>
            </aside>
        </div>
        @if ($testimonials->isNotEmpty())
            @include('theme-atlas::sections.testimonials', ['site' => $site, 'testimonials' => $testimonials])
        @endif
    </section>
</x-site.layout>
