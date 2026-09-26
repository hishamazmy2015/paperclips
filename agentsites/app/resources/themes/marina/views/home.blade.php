<x-site.layout :site="$site" :json-ld="$site->agentJsonLd()">
    @foreach ($site->sections as $section)
        @includeIf('theme-marina::sections.'.$section, ['site' => $site, 'featured' => $featured, 'testimonials' => $testimonials])
    @endforeach
</x-site.layout>
