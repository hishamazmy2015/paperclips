@if ($testimonials->isNotEmpty())
<section class="section" id="testimonials">
    <x-site.section-heading :title="__('site.sections.testimonials')" />
    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($testimonials as $t)
            <figure class="card p-6">
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
