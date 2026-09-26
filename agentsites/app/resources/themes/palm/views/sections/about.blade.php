<section class="section rule" id="about">
    <p class="eyebrow">{{ __('site.nav.about') }}</p>
    <h2 class="h2 mt-3">{{ __('site.sections.about', ['name' => $site->name]) }}</h2>
    <div class="prose-site mt-6">
        <p>{{ $site->bio() }}</p>
        @if ($site->about() !== $site->bio())
            <p>{{ $site->about() }}</p>
        @endif
    </div>
    @if ($site->whyMe() !== [])
        <dl class="mt-8 grid gap-6 sm:grid-cols-3">
            @foreach ($site->whyMe() as $item)
                <div class="card"><dt class="font-semibold">{{ $item['title'] }}</dt><dd class="mt-1 text-sm text-muted">{{ $item['text'] }}</dd></div>
            @endforeach
        </dl>
    @endif
    <a href="{{ $site->url('about') }}" class="gold-link mt-8 inline-block">{{ __('site.nav.about') }} →</a>
</section>
