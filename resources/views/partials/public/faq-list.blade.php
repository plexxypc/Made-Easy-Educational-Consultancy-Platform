{{--
    FAQ questions as native <details> (no JavaScript). $faqs: Faq::shown().
    $heading_tag: "h2" on /faq, "h3" inside the homepage section.
--}}
<div class="divide-y-2 divide-ink overflow-hidden rounded-2xl border-2 border-ink bg-white">
    @foreach ($faqs as $faq)
        <details class="group">
            <summary class="flex min-h-[56px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-surface [&::-webkit-details-marker]:hidden">
                <{{ $heading_tag }} class="font-sans text-base font-semibold text-ink sm:text-lg">{{ $faq->question }}</{{ $heading_tag }}>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-ink group-open:bg-accent group-open:text-white" aria-hidden="true">
                    <svg class="h-4 w-4 group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                </span>
            </summary>
            <div class="space-y-3 px-5 pb-5 text-ink/80">
                @foreach ($faq->answerParagraphs() as $paragraph)
                    <p class="whitespace-pre-line">{{ $paragraph }}</p>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
