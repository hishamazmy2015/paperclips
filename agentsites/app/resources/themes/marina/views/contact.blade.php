<x-site.layout :site="$site" :title="__('site.nav.contact')" canonical-path="contact">
    <section class="section">
        <x-site.section-heading level="1" :eyebrow="$site->agency()" :title="__('site.contact.title', ['name' => $site->name])" />
        <p class="mb-8 max-w-2xl text-lg text-muted">{{ __('site.contact.intro') }} · {{ __('site.contact.hours') }}</p>
        @include('theme-marina::sections.contact', ['site' => $site])
        @include('theme-marina::sections.map', ['site' => $site])
    </section>
</x-site.layout>
