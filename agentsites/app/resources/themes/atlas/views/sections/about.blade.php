<section class="section" id="about">
    <div class="grid gap-10 md:grid-cols-5">
        <div class="md:col-span-3">
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
            <ul class="space-y-4 md:col-span-2">
                @foreach ($site->whyMe() as $item)
                    <li class="card p-5">
                        <p class="font-semibold">{{ $item['title'] }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $item['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
