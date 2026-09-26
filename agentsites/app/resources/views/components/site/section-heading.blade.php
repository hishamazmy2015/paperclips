@props(['eyebrow' => '', 'title', 'level' => 2])
@php($tag = (int) $level === 1 ? 'h1' : 'h2')
<div class="mb-8">
    @if ($eyebrow !== '')
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <{{ $tag }} class="h2">{{ $title }}</{{ $tag }}>
</div>
