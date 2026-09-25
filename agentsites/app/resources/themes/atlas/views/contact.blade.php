<x-site.layout :site="$site" :title="__('site.nav.contact')" canonical-path="contact">
    <section class="section">
        <x-site.section-heading :title="__('site.contact.title', ['name' => $site->name])" />
        <p class="mb-8 max-w-2xl text-lg text-muted">{{ __('site.contact.intro') }} · {{ __('site.contact.hours') }}</p>
        @include('theme-atlas::sections.contact', ['site' => $site])
        @include('theme-atlas::sections.map', ['site' => $site])
    </section>
</x-site.layout>
