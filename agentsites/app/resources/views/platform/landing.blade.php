<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ __('platform.landing.headline') }}</title>
    <meta name="description" content="{{ __('platform.landing.headline') }}">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-white text-neutral-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-8 px-6 text-center">
        <a href="?lang={{ $locale === 'ar' ? 'en' : 'ar' }}" class="rounded-full border px-3 py-1 text-sm">{{ $locale === 'ar' ? 'English' : 'العربية' }}</a>
        <h1 class="text-4xl font-bold sm:text-6xl">{{ __('platform.landing.headline') }}</h1>
        <a href="/start" class="rounded-full bg-neutral-900 px-8 py-4 text-lg font-semibold text-white">{{ __('platform.landing.cta') }}</a>
        <p class="text-neutral-500">{{ __('platform.landing.counter', ['count' => $published]) }}</p>
    </main>
</body>
</html>
