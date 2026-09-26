@php($years = (int) ($site->config['identity']['years_experience'] ?? 0))
@php($languages = (array) ($site->config['identity']['languages'] ?? []))
<section class="section" id="stats">
    <dl class="card grid gap-6 p-6 sm:grid-cols-3">
        @if ($years > 0)
            <div><dt class="text-sm text-muted">{{ __('site.stats.years') }}</dt><dd class="text-3xl font-bold text-primary">{{ $years }}+</dd></div>
        @endif
        <div><dt class="text-sm text-muted">{{ __('site.stats.areas') }}</dt><dd class="text-3xl font-bold text-primary">{{ count($site->areas) }}</dd></div>
        @if ($languages !== [])
            <div><dt class="text-sm text-muted">{{ __('site.stats.languages') }}</dt><dd class="text-xl font-bold">{{ implode(' · ', array_map('strtoupper', $languages)) }}</dd></div>
        @endif
    </dl>
</section>
