@props(['site', 'title' => '', 'description' => '', 'canonicalPath' => '', 'jsonLd' => null, 'ogImage' => null])
@php($ogImage = $site->absoluteUrl($ogImage ?: $site->ogImage()))
@php($description = $description !== '' ? $description : $site->metaDescription())
<!DOCTYPE html>
<html lang="{{ $site->locale }}" dir="{{ $site->dir }}" data-dark="{{ $site->darkMode() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->title($title) }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($site->noindex())
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ $site->canonical($canonicalPath) }}">
    <link rel="alternate" hreflang="{{ $site->locale }}" href="{{ $site->canonical($canonicalPath) }}">
    <link rel="alternate" hreflang="{{ $site->otherLocale }}" href="https://{{ $site->tenant->primaryHost() }}{{ $site->switchLocaleUrl($site->url($canonicalPath)) }}">
    <link rel="alternate" hreflang="x-default" href="https://{{ $site->tenant->primaryHost() }}{{ $site->defaultLocaleUrl($canonicalPath) }}">
    <meta property="og:title" content="{{ $site->title($title) }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $site->canonical($canonicalPath) }}">
    <meta property="og:site_name" content="{{ $site->name }}">
    <meta property="og:locale" content="{{ $site->locale === 'ar' ? 'ar_AE' : 'en_AE' }}">
    @if ($ogImage !== null)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $ogImage }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $site->title($title) }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="theme-color" content="{{ $site->palette['--c-primary'] ?? '#000000' }}">
    <style>:root{@foreach ($site->palette as $name => $value){{ $name }}:{{ $value }};@endforeach}</style>
    @foreach ($site->fontPreloads() as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    @vite($site->cssEntry)
    @if ($jsonLd !== null)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
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
