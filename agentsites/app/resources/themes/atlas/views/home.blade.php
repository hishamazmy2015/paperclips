<x-site.layout :site="$site" :json-ld="$site->agentJsonLd()">
    @foreach ($site->sections as $section)
        @includeIf('theme-atlas::sections.'.$section, ['site' => $site, 'featured' => $featured, 'testimonials' => $testimonials])
    @endforeach
</x-site.layout>
