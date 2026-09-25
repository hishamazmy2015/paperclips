<x-site.layout :site="$site" :title="__('site.not_found.title')">
    <section class="section text-center">
        <p class="eyebrow">404</p>
        <h1 class="h2">{{ __('site.not_found.title') }}</h1>
        <p class="mt-2 text-muted">{{ __('site.not_found.text') }}</p>
        <a href="{{ $site->url() }}" class="btn-primary mt-6">{{ __('site.not_found.back') }}</a>
    </section>
</x-site.layout>
