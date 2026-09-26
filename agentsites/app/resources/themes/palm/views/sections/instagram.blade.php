@php($instagram = $site->socials()['instagram'] ?? '')
@if ($instagram !== '')
<section class="section rule" id="instagram">
    <p class="eyebrow">{{ __('site.sections.instagram') }}</p>
    <a href="{{ $instagram }}" rel="noopener nofollow" target="_blank" class="gold-link mt-4 inline-block">{{ $instagram }}</a>
</section>
@endif
