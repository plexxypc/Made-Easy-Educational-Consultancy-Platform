@php($input_classes = 'block min-h-[48px] w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')
@php($selected_slug = old('service', $services->count() === 1 ? $services->first()->slug : null))

<x-public-layout title="Buy Result Checker" description="Buy WAEC, NECO, NABTEB and GCE result checker PINs online. Pay securely with Paystack and get your PIN by email.">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Buy Result Checker</h1>
        <p class="mt-3 text-lg text-slate-700">
            Choose your result checker, pay securely with Paystack, and we'll email your PIN straight away.
            It will also be shown on your order page.
        </p>

        @if ($services->isEmpty())
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-lg font-semibold text-slate-900">Result checkers aren't available online right now.</p>
                <p class="mt-2 text-slate-600">Contact us and we'll help you get one.</p>
                <x-public.button :href="route('contact')" class="mt-4">Contact Us</x-public.button>
            </div>
        @else
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

            <form method="POST" action="{{ route('result-checker.buy') }}" class="mt-8 space-y-8">
                @csrf

                <fieldset>
                    <legend class="text-lg font-semibold text-slate-900">1. Choose a result checker</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($services as $service)
                            <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 border-slate-200 bg-white p-5 shadow-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand-300">
                                <input type="radio" name="service" value="{{ $service->slug }}" class="sr-only" required @checked($selected_slug === $service->slug)>
                                <span class="text-lg font-semibold text-slate-900">{{ $service->name }}</span>
                                <span class="mt-1 text-2xl font-bold text-slate-900">{{ $service->priceLabel() }}</span>
                                <span class="mt-1 text-sm text-slate-600">per PIN</span>
                                @if ($service->available_pins_count > 0)
                                    <span class="mt-3 text-sm font-medium text-emerald-700">Delivered instantly by email</span>
                                @else
                                    <span class="mt-3 text-sm font-medium text-amber-800">Delivered by our team, usually within working hours</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="quantity" class="block text-lg font-semibold text-slate-900">2. How many?</label>
                    <select id="quantity" name="quantity" required class="{{ $input_classes }} mt-2 sm:w-40">
                        @for ($quantity = 1; $quantity <= $max_quantity; $quantity++)
                            <option value="{{ $quantity }}" @selected((int) old('quantity', 1) === $quantity)>{{ $quantity }}</option>
                        @endfor
                    </select>
                    <p class="mt-1 text-sm text-slate-600">Each PIN checks one result. Buying for several children? Choose up to {{ $max_quantity }}.</p>
                </div>

                <fieldset class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <legend class="px-1 text-lg font-semibold text-slate-900">3. Your contact details</legend>

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
                        <p id="email-help" class="mt-1 text-sm text-slate-500">Your PIN and receipt are sent here.</p>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-semibold text-slate-800">Phone number</label>
                        <input id="phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel"
                            value="{{ old('phone') }}"
                            aria-describedby="phone-help"
                            @error('phone') aria-invalid="true" @enderror
                            class="{{ $input_classes }} mt-1">
                        <p id="phone-help" class="mt-1 text-sm text-slate-500">Preferably your WhatsApp number, in case we need to reach you.</p>
                    </div>
                </fieldset>

                <div>
                    <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                        Buy now
                    </button>
                    <p class="mt-3 text-sm text-slate-600">
                        You'll pay on Paystack (card, bank transfer or USSD), which shows the total before you pay.
                        You don't need an account.
                    </p>
                </div>
            </form>
        @endif
    </section>
</x-public-layout>
