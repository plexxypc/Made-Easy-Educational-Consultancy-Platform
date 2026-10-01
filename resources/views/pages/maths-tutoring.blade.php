{{--
    Mathematics online tutorials (PDF section 12). Promotion only: lessons are
    arranged directly over WhatsApp, never booked or paid on this site.
    CLIENT REVIEW: the levels and format below are general; confirm them
    (and add times/fees if the client wants them public).
--}}
@php($whatsapp_url = \App\Support\WhatsApp::link($whatsapp_message))

<x-public-layout
    title="Online Mathematics Tutorials"
    description="Online Mathematics tutoring for WAEC, NECO, JAMB and school exams. Arrange your lessons with us directly on WhatsApp."
    :whatsapp-message="$whatsapp_message"
>
    <section class="bg-gradient-to-b from-brand-50 to-slate-50">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Online Mathematics Tutorials</h1>
            <p class="mt-4 text-lg leading-relaxed text-slate-700">
                Struggling with Mathematics, or aiming for a better grade? We offer online Mathematics lessons for
                secondary school students preparing for WAEC, NECO, JAMB (UTME) and school exams.
            </p>

            @if ($whatsapp_url)
                <a href="{{ $whatsapp_url }}" target="_blank" rel="noopener" class="mt-8 inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-[#25D366] px-8 text-base font-semibold text-slate-900 shadow-sm hover:bg-[#1ebe5b] focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-300 sm:w-auto">
                    Ask about tutoring on WhatsApp
                </a>
            @else
                <p class="mt-8"><a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a> to arrange tutoring.</p>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h2 class="text-2xl font-bold text-slate-900">How it works</h2>
        <ol class="mt-6 space-y-4">
            @foreach ([
                ['title' => 'Message us on WhatsApp', 'body' => "Tap the button and send the message. Tell us the student's class, the exam they're preparing for and the topics they find hard."],
                ['title' => 'Agree a plan', 'body' => 'We reply with available times, the lesson format and fees, and agree a schedule that suits you.'],
                ['title' => 'Learn online', 'body' => 'Lessons take place online, so the student can join from home on a phone or computer.'],
            ] as $step)
                <li class="flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-700 text-base font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $step['title'] }}</h3>
                        <p class="mt-1 text-slate-600">{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 text-slate-700 shadow-sm">
            Tutoring is arranged directly with our team on WhatsApp. It isn't booked or paid for on this website,
            and we'll never ask you to pay through a link you didn't get from us.
        </p>
    </section>
</x-public-layout>
