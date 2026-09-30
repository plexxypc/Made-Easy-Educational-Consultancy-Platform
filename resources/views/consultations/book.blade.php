@php($input_classes = 'block min-h-[48px] w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout title="Book a Consultation" description="Book a one-to-one admission consultation online, by WhatsApp or Google Meet. Choose a time and pay securely with Paystack.">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Book a Consultation</h1>

        @if ($service === null)
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-lg font-semibold text-slate-900">Online booking isn't open right now.</p>
                <p class="mt-2 text-slate-600">Contact us and we'll arrange a consultation with you.</p>
                <x-public.button :href="route('contact')" class="mt-4">Contact Us</x-public.button>
            </div>
        @else
            <p class="mt-3 text-lg text-slate-700">
                {{ $service->name }}: <strong class="text-slate-900">{{ $service->priceLabel() }}</strong>.
                Choose a time, pay securely with Paystack, and we'll email your confirmation.
            </p>
            @if (filled($service->description))
                <p class="mt-2 text-slate-600">{{ $service->description }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">
                    <p class="font-semibold">Please check the form.</p>
                    <ul class="mt-2 list-inside list-disc text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($slots_by_date->isEmpty())
                <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-lg font-semibold text-slate-900">No consultation times are available at the moment.</p>
                    <p class="mt-2 text-slate-600">New times are added regularly. Check back soon, or contact us to arrange one.</p>
                    <x-public.button :href="route('contact')" class="mt-4">Contact Us</x-public.button>
                </div>
            @else
                <form method="POST" action="{{ route('book-consultation.book') }}" class="mt-8 space-y-8">
                    @csrf

                    <fieldset>
                        <legend class="text-lg font-semibold text-slate-900">1. Choose a date and time</legend>
                        <p class="mt-1 text-sm text-slate-600">All times are Nigerian time (WAT).</p>

                        <div class="mt-4 space-y-6">
                            @foreach ($slots_by_date as $date => $slots)
                                <div>
                                    <h2 class="text-base font-bold text-slate-800">{{ $slots->first()->dateLabel() }}</h2>
                                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                        @foreach ($slots as $slot)
                                            <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-slate-200 bg-white p-4 shadow-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand-300">
                                                <input type="radio" name="slot" value="{{ $slot->id }}" class="sr-only" required @checked((int) old('slot') === $slot->id)>
                                                <span class="text-lg font-semibold text-slate-900">{{ $slot->timeLabel() }}</span>
                                                <span class="mt-1 text-sm font-medium text-slate-700">{{ $slot->consultation_type->getLabel() }}</span>
                                                @if ($slot->capacity > 1)
                                                    <span class="mt-1 text-sm text-slate-500">{{ $slot->remainingCapacity() }} of {{ $slot->capacity }} places left</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <legend class="px-1 text-lg font-semibold text-slate-900">2. Your contact details</legend>

                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-800">Full name</label>
                            <input id="name" name="name" type="text" required maxlength="255" autocomplete="name"
                                value="{{ old('name', auth()->user()?->name) }}"
                                @error('name') aria-invalid="true" @enderror
                                class="{{ $input_classes }} mt-1">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-800">Email address</label>
                            <input id="email" name="email" type="email" required maxlength="255" autocomplete="email"
                                value="{{ old('email', auth()->user()?->email) }}"
                                aria-describedby="email-help"
                                @error('email') aria-invalid="true" @enderror
                                class="{{ $input_classes }} mt-1">
                            <p id="email-help" class="mt-1 text-sm text-slate-500">Your confirmation, receipt and any meeting link are sent here.</p>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-slate-800">Phone number</label>
                            <input id="phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel"
                                value="{{ old('phone') }}"
                                aria-describedby="phone-help"
                                @error('phone') aria-invalid="true" @enderror
                                class="{{ $input_classes }} mt-1">
                            <p id="phone-help" class="mt-1 text-sm text-slate-500">Your WhatsApp number: WhatsApp consultations are called on it.</p>
                        </div>
                    </fieldset>

                    <div>
                        <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                            Book and pay {{ $service->priceLabel() }}
                        </button>
                        <p class="mt-3 text-sm text-slate-600">
                            We hold your chosen time for {{ $hold_minutes }} minutes while you pay on Paystack (card, bank transfer or USSD).
                            Your booking is confirmed as soon as your payment is. You don't need an account.
                        </p>
                    </div>
                </form>
            @endif
        @endif
    </section>
</x-public-layout>
