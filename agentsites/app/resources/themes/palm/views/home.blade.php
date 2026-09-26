<x-site.layout :site="$site" :json-ld="$site->agentJsonLd()">
    {{-- Palm is testimonials-forward (spec §10): they follow the hero whatever the section order says. --}}
    @foreach ($site->sections as $section)
        @includeIf('theme-palm::sections.'.$section, ['site' => $site, 'featured' => $featured, 'testimonials' => $testimonials])
        @if ($section === 'hero' && $site->hasSection('testimonials'))
            @include('theme-palm::sections.testimonials', ['site' => $site, 'testimonials' => $testimonials, 'forward' => true])
        @endif
    @endforeach
</x-site.layout>
