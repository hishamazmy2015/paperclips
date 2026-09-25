@props(['title' => null, 'progress' => null, 'total' => 3])
@php($progress = isset($progress) && trim((string) $progress) !== '' ? (int) trim((string) $progress) : null)
@php($locale = app()->getLocale())
@php($other = $locale === 'ar' ? 'en' : 'ar')
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <header class="app-header">
        <a href="{{ route('landing.app') }}" class="app-brand">{{ config('app.name') }}</a>
        @if ($progress !== null)
            <span class="app-progress-label">{{ __('platform.wizard.progress', ['step' => $progress, 'total' => $total]) }}</span>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['lang' => $other]) }}" class="app-lang" lang="{{ $other }}" hreflang="{{ $other }}">{{ $other === 'ar' ? 'العربية' : 'English' }}</a>
    </header>
    @if ($progress !== null)
        <div class="app-progress" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $progress }}">
            <div class="app-progress-bar" style="width: {{ (int) round($progress / max(1, $total) * 100) }}%"></div>
        </div>
    @endif
    <main class="app-main">
        {{ $slot }}
    </main>
</body>
</html>
