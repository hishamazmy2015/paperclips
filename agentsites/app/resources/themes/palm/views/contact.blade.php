<x-site.layout :site="$site" :title="__('site.nav.contact')" canonical-path="contact">
    <section class="section">
        <p class="eyebrow">{{ $site->agency() !== '' ? $site->agency() : __('site.nav.contact') }}</p>
        <h1 class="display mt-3">{{ __('site.contact.title', ['name' => $site->name]) }}</h1>
        <p class="lede mt-6">{{ __('site.contact.intro') }} {{ __('site.contact.hours') }}.</p>
    </section>
    @include('theme-palm::sections.contact', ['site' => $site])
    @include('theme-palm::sections.map', ['site' => $site])
</x-site.layout>
