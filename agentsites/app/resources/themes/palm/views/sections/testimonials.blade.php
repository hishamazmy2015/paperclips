@php($forward = $forward ?? false)
@if ($testimonials->isNotEmpty() && ($forward || ! $site->hasSection('hero')))
<section class="section rule" id="testimonials">
    <p class="eyebrow text-center">{{ __('site.sections.testimonials') }}</p>
    <div class="mt-8 space-y-10">
        @foreach ($testimonials->take(3) as $t)
            <figure class="text-center">
                <blockquote class="text-2xl font-medium leading-snug tracking-tight sm:text-3xl">“{{ $t->text($site->locale) }}”</blockquote>
                <figcaption class="mt-4 text-sm text-muted">
                    @if ($t->rating)<span class="text-accent" aria-label="{{ $t->rating }}/5">{{ str_repeat('★', (int) $t->rating) }}</span> · @endif
                    <span class="font-semibold text-ink">{{ $t->author_name }}</span>@if ($t->author_role) · {{ $t->author_role }}@endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif
