@props(['site', 'title' => '', 'description' => '', 'canonicalPath' => ''])
<!DOCTYPE html>
<html lang="{{ $site->locale }}" dir="{{ $site->dir }}" data-dark="{{ $site->darkMode() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->title($title) }}</title>
    <meta name="description" content="{{ $description !== '' ? $description : $site->metaDescription() }}">
    @if ($site->noindex())
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ $site->canonical($canonicalPath) }}">
    <link rel="alternate" hreflang="{{ $site->locale }}" href="{{ $site->canonical($canonicalPath) }}">
    <link rel="alternate" hreflang="{{ $site->otherLocale }}" href="https://{{ $site->tenant->primaryHost() }}{{ $site->switchLocaleUrl($site->url($canonicalPath)) }}">
    <meta property="og:title" content="{{ $site->title($title) }}">
    <meta property="og:description" content="{{ $description !== '' ? $description : $site->metaDescription() }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $site->canonical($canonicalPath) }}">
    @if (($site->config['branding']['hero_image'] ?? '') !== '')
        <meta property="og:image" content="{{ $site->config['branding']['hero_image'] }}">
    @endif
    <meta name="theme-color" content="{{ $site->palette['--c-primary'] ?? '#000000' }}">
    <style>:root{@foreach ($site->palette as $name => $value){{ $name }}:{{ $value }};@endforeach}</style>
    @vite($site->cssEntry)
</head>
<body class="min-h-screen font-sans">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">{{ __('site.skip') }}</a>
    <x-site.header :site="$site" />
    <main id="main">
        {{ $slot }}
    </main>
    <x-site.footer :site="$site" />
    <x-site.whatsapp-bar :site="$site" />
</body>
</html>
