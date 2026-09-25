<x-layouts.app :title="__('platform.home.title')">
    <section class="app-card" data-test="home">
        <h1 class="app-h1">{{ $tenant->displayName() }}</h1>
        <p class="app-lead">
            <span class="app-status app-status-{{ $tenant->status }}">{{ __('platform.home.status_'.$tenant->status) }}</span>
            <span dir="ltr">{{ preg_replace('#^https?://#', '', rtrim($url, '/')) }}</span>
        </p>

        @if ($tenant->isDraft())
            <a href="{{ route('onboarding', ['step' => max(1, $tenant->onboarding_step)]) }}" class="btn-primary" data-test="resume">{{ __('platform.home.resume') }}</a>
            <a href="{{ $previewUrl }}" class="app-link" target="_blank" rel="noopener">{{ __('platform.home.preview') }}</a>
        @else
            <a href="{{ $url }}" class="btn-primary" target="_blank" rel="noopener">{{ __('platform.success.open_site') }}</a>
            <ul class="app-checklist">
                @foreach ($checklist as $key => $done)
                    <li class="{{ $done ? 'is-done' : '' }}"><span class="app-check" aria-hidden="true">{{ $done ? '✓' : '' }}</span>{{ __('platform.success.'.($key === 'domain' ? 'connect_domain' : 'add_'.$key)) }}</li>
                @endforeach
            </ul>
            <p class="app-fineprint">{{ __('platform.home.dashboard_soon') }}</p>
        @endif

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="app-link">{{ __('platform.home.sign_out') }}</button>
        </form>
    </section>
</x-layouts.app>
