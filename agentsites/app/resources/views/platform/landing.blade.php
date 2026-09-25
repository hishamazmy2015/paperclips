<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ __('platform.landing.headline') }}</title>
    <meta name="description" content="{{ __('platform.landing.headline') }}">
    @vite('resources/css/app.css')
</head>
<body class="app-body">
    <main class="landing">
        <a href="?lang={{ $locale === 'ar' ? 'en' : 'ar' }}" class="app-lang landing-lang">{{ $locale === 'ar' ? 'English' : 'العربية' }}</a>
        <h1 class="landing-h1">{{ __('platform.landing.headline') }}</h1>
        <a href="{{ $startUrl }}" class="btn-primary landing-cta" data-test="landing-cta">{{ __('platform.landing.cta') }}</a>
        <p class="landing-counter" data-test="published-count">{{ __('platform.landing.counter', ['count' => $published]) }}</p>
        <ul class="landing-themes" aria-label="{{ __('platform.wizard.theme') }}">
            @foreach ($themes as $theme)
                <li>
                    <img src="{{ $theme['preview'] }}" alt="{{ $theme['name'] }}" width="240" height="420" loading="lazy">
                    <span>{{ $theme['name'] }}@if (! $theme['installed']) · {{ __('platform.wizard.coming_soon') }}@endif</span>
                </li>
            @endforeach
        </ul>
    </main>
</body>
</html>
