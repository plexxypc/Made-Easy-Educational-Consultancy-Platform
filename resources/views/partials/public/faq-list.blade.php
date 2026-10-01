{{--
    FAQ questions as native <details> (no JavaScript). $faqs: Faq::shown().
    $heading_tag: "h2" on /faq, "h3" inside the homepage section.
--}}
<div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    @foreach ($faqs as $faq)
        <details class="group">
            <summary class="flex min-h-[56px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                <{{ $heading_tag }} class="text-base font-semibold text-slate-900 sm:text-lg">{{ $faq->question }}</{{ $heading_tag }}>
                <svg class="h-5 w-5 shrink-0 text-slate-500 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
            </summary>
            <div class="space-y-3 px-5 pb-5 text-slate-700">
                @foreach ($faq->answerParagraphs() as $paragraph)
                    <p class="whitespace-pre-line">{{ $paragraph }}</p>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
