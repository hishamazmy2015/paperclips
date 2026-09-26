<!DOCTYPE html>
<html lang="{{ $site->locale }}" dir="{{ $site->dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('site.paused.title') }} — {{ $site->name }}</title>
    <style>:root{@foreach ($site->palette as $name => $value){{ $name }}:{{ $value }};@endforeach}</style>
    @vite($site->cssEntry)
</head>
<body class="grid min-h-screen place-items-center font-sans">
    <main class="section max-w-lg text-center">
        <p class="eyebrow">{{ $site->name }}</p>
        <h1 class="h2">{{ __('site.paused.title') }}</h1>
        <p class="mt-2 text-muted">{{ __('site.paused.text') }}</p>
    </main>
</body>
</html>
