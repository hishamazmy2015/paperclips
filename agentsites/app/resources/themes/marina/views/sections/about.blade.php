<section class="section border-t border-line" id="about">
    <div class="grid gap-10 md:grid-cols-2">
        <div>
            <x-site.section-heading :eyebrow="__('site.nav.about')" :title="__('site.sections.about', ['name' => $site->name])" />
            <div class="prose-site max-w-none">
                <p>{{ $site->bio() }}</p>
                @if ($site->about() !== $site->bio())
                    <p>{{ $site->about() }}</p>
                @endif
            </div>
            <a href="{{ $site->url('about') }}" class="btn-outline mt-4">{{ __('site.nav.about') }} →</a>
        </div>
        @if ($site->whyMe() !== [])
            <ol class="space-y-4">
                @foreach ($site->whyMe() as $i => $item)
                    <li class="flex gap-4">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-contrast">{{ $i + 1 }}</span>
                        <div><p class="font-semibold">{{ $item['title'] }}</p><p class="mt-1 text-sm text-muted">{{ $item['text'] }}</p></div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
