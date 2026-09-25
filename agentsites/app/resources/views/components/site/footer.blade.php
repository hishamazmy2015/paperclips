@props(['site'])
<footer class="mt-16 border-t border-line bg-surface pb-24 md:pb-8">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3">
        <div>
            <p class="text-lg font-bold">{{ $site->name }}</p>
            @if ($site->agency() !== '')
                <p class="text-muted">{{ $site->agency() }}</p>
            @endif
            @if (($site->config['identity']['license_no'] ?? '') !== '')
                <p class="mt-2 text-sm text-muted">{{ __('site.footer.license', ['license' => $site->config['identity']['license_no']]) }}</p>
            @endif
            @if (($site->config['identity']['brn'] ?? '') !== '')
                <p class="text-sm text-muted">{{ __('site.footer.brn', ['brn' => $site->config['identity']['brn']]) }}</p>
            @endif
        </div>
        <div>
            <p class="mb-2 font-semibold">{{ __('site.nav.menu') }}</p>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ $site->url('listings') }}" class="hover:text-primary">{{ __('site.nav.listings') }}</a></li>
                <li><a href="{{ $site->url('about') }}" class="hover:text-primary">{{ __('site.nav.about') }}</a></li>
                <li><a href="{{ $site->url('contact') }}" class="hover:text-primary">{{ __('site.nav.contact') }}</a></li>
                @foreach ($site->areas as $area)
                    <li><a href="{{ $site->areaUrl($area) }}" class="hover:text-primary">{{ $area }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <p class="mb-2 font-semibold">{{ __('site.sections.contact') }}</p>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ $site->whatsappUrl() }}" rel="noopener" target="_blank" class="hover:text-primary">{{ __('site.cta.whatsapp') }}: <span dir="ltr">{{ $site->whatsapp() }}</span></a></li>
                @if ($site->email() !== '')
                    <li><a href="mailto:{{ $site->email() }}" class="hover:text-primary">{{ $site->email() }}</a></li>
                @endif
                @foreach ($site->socials() as $network => $url)
                    <li><a href="{{ $url }}" rel="noopener nofollow" target="_blank" class="capitalize hover:text-primary">{{ $network }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 pb-4 text-xs text-muted sm:px-6">
        <span>{{ __('site.footer.rights', ['year' => date('Y'), 'name' => $site->name]) }}</span>
        <span>
            {{ __('site.footer.powered_by', ['platform' => config('app.name')]) }} ·
            <a href="mailto:abuse@{{ config('platform.base_domain') }}?subject={{ rawurlencode('Report: '.$site->tenant->primaryHost()) }}" class="underline">{{ __('site.footer.report_abuse') }}</a>
        </span>
    </div>
</footer>
