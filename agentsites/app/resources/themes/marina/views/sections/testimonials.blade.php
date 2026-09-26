@if ($testimonials->isNotEmpty())
<section class="section border-t border-line" id="testimonials">
    <x-site.section-heading :title="__('site.sections.testimonials')" />
    <div class="scroll-row">
        @foreach ($testimonials as $t)
            <figure class="card p-5">
                @if ($t->rating)
                    <p class="text-accent" aria-label="{{ $t->rating }}/5">{{ str_repeat('★', (int) $t->rating) }}</p>
                @endif
                <blockquote class="mt-2 leading-relaxed">“{{ $t->text($site->locale) }}”</blockquote>
                <figcaption class="mt-4 text-sm font-semibold">{{ $t->author_name }}@if ($t->author_role) <span class="font-normal text-muted">· {{ $t->author_role }}</span>@endif</figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif
