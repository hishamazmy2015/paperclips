@php($years = (int) ($site->config['identity']['years_experience'] ?? 0))
@php($languages = (array) ($site->config['identity']['languages'] ?? []))
<section class="section" id="stats">
    <dl class="grid gap-6 rounded-3xl bg-primary p-8 text-primary-contrast sm:grid-cols-3">
        @if ($years > 0)
            <div><dt class="text-sm opacity-80">{{ __('site.stats.years') }}</dt><dd class="text-4xl font-bold">{{ $years }}+</dd></div>
        @endif
        <div><dt class="text-sm opacity-80">{{ __('site.stats.areas') }}</dt><dd class="text-4xl font-bold">{{ count($site->areas) }}</dd></div>
        @if ($languages !== [])
            <div><dt class="text-sm opacity-80">{{ __('site.stats.languages') }}</dt><dd class="text-2xl font-bold">{{ implode(' · ', array_map('strtoupper', $languages)) }}</dd></div>
        @endif
    </dl>
</section>
