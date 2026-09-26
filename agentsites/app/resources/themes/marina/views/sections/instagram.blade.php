@php($instagram = $site->socials()['instagram'] ?? '')
@if ($instagram !== '')
<section class="section" id="instagram">
    <x-site.section-heading :title="__('site.sections.instagram')" />
    <a href="{{ $instagram }}" rel="noopener nofollow" target="_blank" class="btn-outline">{{ $instagram }}</a>
</section>
@endif
