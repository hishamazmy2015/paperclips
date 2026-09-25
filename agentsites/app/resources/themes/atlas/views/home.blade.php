<x-site.layout :site="$site">
    @foreach ($site->sections as $section)
        @includeIf('theme-atlas::sections.'.$section, ['site' => $site, 'featured' => $featured, 'testimonials' => $testimonials])
    @endforeach
</x-site.layout>
