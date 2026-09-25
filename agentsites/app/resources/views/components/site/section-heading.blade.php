@props(['eyebrow' => '', 'title'])
<div class="mb-8">
    @if ($eyebrow !== '')
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <h2 class="h2">{{ $title }}</h2>
</div>
