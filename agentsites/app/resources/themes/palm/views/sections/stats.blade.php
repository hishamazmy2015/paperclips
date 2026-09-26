@php($years = (int) ($site->config['identity']['years_experience'] ?? 0))
@php($languages = (array) ($site->config['identity']['languages'] ?? []))
<section class="section rule" id="stats">
    <dl class="grid gap-8 text-center sm:grid-cols-3">
        @if ($years > 0)
            <div><dd class="text-5xl font-bold tracking-tighter text-accent">{{ $years }}+</dd><dt class="mt-1 text-sm text-muted">{{ __('site.stats.years') }}</dt></div>
        @endif
        <div><dd class="text-5xl font-bold tracking-tighter text-accent">{{ count($site->areas) }}</dd><dt class="mt-1 text-sm text-muted">{{ __('site.stats.areas') }}</dt></div>
        @if ($languages !== [])
            <div><dd class="text-2xl font-bold">{{ implode(' · ', array_map('strtoupper', $languages)) }}</dd><dt class="mt-1 text-sm text-muted">{{ __('site.stats.languages') }}</dt></div>
        @endif
    </dl>
</section>
