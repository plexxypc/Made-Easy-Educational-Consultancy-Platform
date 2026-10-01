{{--
    Course & cut-off search (PDF section 11). Plain GET form, no JavaScript.
    $results: paginator, or null before the form is submitted. Matching rules: AdmissionRequirement::matching().
--}}
@php
    $input_classes = 'block w-full min-h-[48px] rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500';
    $field_value = fn (string $key) => old($key, request()->query($key));
@endphp
<x-public-layout
    title="Course & Cut-off Mark Search"
    description="Enter your JAMB score, O'Level credits, course and state to see which universities, polytechnics and colleges of education you meet the cut-off mark for."
    :canonical-url="route('admission.search')"
>
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('services.category', ['category' => \App\Support\PublicNavigation::ADMISSION_CATEGORY_SLUG]) }}" class="hover:underline">Admission</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Course search</span>
        </nav>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">Can I get in with my score?</h1>
        <p class="mt-3 max-w-2xl text-slate-700">
            Fill in what you know. Every box is optional: leave one empty to see more options.
        </p>

        <form method="GET" action="{{ route('admission.search') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" role="search" aria-label="Course and cut-off search">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="score" class="block text-sm font-semibold text-slate-800">JAMB score <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="score" name="score" type="number" inputmode="numeric" min="0" max="{{ \App\Models\AdmissionRequirement::MAX_SCORE }}" step="1"
                        value="{{ $field_value('score') }}" placeholder="e.g. 245"
                        @error('score') aria-invalid="true" aria-describedby="score-error" @enderror
                        class="{{ $input_classes }} mt-1 @error('score') border-red-500 @enderror">
                    @error('score')<p id="score-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="credits" class="block text-sm font-semibold text-slate-800">O'Level credits <span class="font-normal text-slate-500">(optional)</span></label>
                    <select id="credits" name="credits"
                        @error('credits') aria-invalid="true" aria-describedby="credits-error" @enderror
                        class="{{ $input_classes }} mt-1 @error('credits') border-red-500 @enderror">
                        <option value="">Any</option>
                        @foreach (range(1, \App\Models\AdmissionRequirement::MAX_CREDITS) as $count)
                            <option value="{{ $count }}" @selected((string) $field_value('credits') === (string) $count)>{{ $count }} {{ $count === 1 ? 'credit' : 'credits' }}</option>
                        @endforeach
                    </select>
                    @error('credits')<p id="credits-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="course" class="block text-sm font-semibold text-slate-800">Preferred course <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="course" name="course" type="text" list="course-names" maxlength="100" autocomplete="off"
                        value="{{ $field_value('course') }}" placeholder="e.g. Computer Science"
                        @error('course') aria-invalid="true" aria-describedby="course-error" @enderror
                        class="{{ $input_classes }} mt-1 @error('course') border-red-500 @enderror">
                    <datalist id="course-names">
                        @foreach ($course_names as $course_name)
                            <option value="{{ $course_name }}"></option>
                        @endforeach
                    </datalist>
                    @error('course')<p id="course-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="state" class="block text-sm font-semibold text-slate-800">State <span class="font-normal text-slate-500">(optional)</span></label>
                    <select id="state" name="state"
                        @error('state') aria-invalid="true" aria-describedby="state-error" @enderror
                        class="{{ $input_classes }} mt-1 @error('state') border-red-500 @enderror">
                        <option value="">Any state</option>
                        @foreach ($states as $state_option)
                            <option value="{{ $state_option->value }}" @selected($field_value('state') === $state_option->value)>{{ $state_option->getLabel() }}</option>
                        @endforeach
                    </select>
                    @error('state')<p id="state-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                    Search
                </button>
                @if ($results !== null)
                    <a href="{{ route('admission.search') }}" class="text-sm font-semibold text-brand-700 underline">Clear search</a>
                @endif
            </div>
        </form>

        @if ($results !== null)
            <section class="mt-8" aria-labelledby="results-heading">
                <h2 id="results-heading" class="text-xl font-bold text-slate-900">
                    {{ $results->total() === 0 ? 'No matches' : trans_choice(':count match|:count matches', $results->total()) }}
                </h2>
                @php
                    $conditions = array_filter([
                        $course_text !== null ? 'the course name contains “'.$course_text.'”' : null,
                        $state !== null ? 'the institution is in '.$state->getLabel() : null,
                        $score !== null ? 'the cut-off mark is '.$score.' or lower' : null,
                        $credits !== null ? "no more than {$credits} O'Level ".\Illuminate\Support\Str::plural('credit', $credits).' are required' : null,
                    ]);
                @endphp
                <p class="mt-1 text-sm text-slate-600">
                    {{ $conditions === []
                        ? 'Showing every course we have cut-off marks for. Add your score, credits, course or state to narrow it down.'
                        : 'Showing courses where '.\Illuminate\Support\Arr::join($conditions, ', ', ' and ').'. Highest cut-off first.' }}
                </p>

                @if ($results->isEmpty())
                    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-6">
                        <p class="font-semibold text-slate-900">Nothing matches all of that yet.</p>
                        <p class="mt-2 text-slate-600">Try leaving the state or course empty, or check the course spelling. Our team can also suggest options for your score.</p>
                    </div>
                @else
                    <ul class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($results as $requirement)
                            <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $requirement->course->name }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $requirement->institution->name }}</h3>
                                @if ($requirement->institution->descriptionLabel() !== '')
                                    <p class="text-sm text-slate-600">{{ $requirement->institution->descriptionLabel() }}</p>
                                @endif
                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <dt class="text-slate-600">JAMB cut-off</dt>
                                        <dd class="text-lg font-bold text-slate-900">{{ $requirement->cut_off_mark }}</dd>
                                        @if ($score !== null)
                                            <dd class="text-emerald-700">Your score: {{ $score }}</dd>
                                        @endif
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <dt class="text-slate-600">O'Level credits</dt>
                                        <dd class="text-lg font-bold text-slate-900">{{ $requirement->olevel_credits_required }}</dd>
                                        @if ($credits !== null)
                                            <dd class="text-emerald-700">You have: {{ $credits }}</dd>
                                        @endif
                                    </div>
                                </dl>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6">{{ $results->links() }}</div>
                @endif
            </section>
        @endif

        <p class="mt-8 max-w-3xl text-sm text-slate-600">
            For guidance only. Cut-off marks are set by each institution and can change every admission year, and many also
            consider Post-UTME scores and specific O'Level subjects. Always confirm on the institution's website or JAMB CAPS.
        </p>

        <div class="mt-6 flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-50 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-lg font-semibold text-slate-900">Want help choosing and applying?</p>
                <p class="text-sm text-slate-700">Our team will look at your scores and guide you through your admission options.</p>
            </div>
            <x-public.button :href="$admission_guidance_url" variant="accent">Get Admission Guidance</x-public.button>
        </div>
    </section>
</x-public-layout>
