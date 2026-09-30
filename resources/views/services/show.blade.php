@php($is_quote = $service->fulfilment_type === \App\Enums\FulfilmentType::Quote)
@php($input_classes = 'block min-h-[48px] w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout :title="$service->name" :description="$service->description">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('services.index') }}" class="hover:underline">Services</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $category->publicUrl() }}" class="hover:underline">{{ $category->name }}</a>
        </nav>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ $service->name }}</h1>

        @if (filled($service->description))
            <p class="mt-3 text-lg text-slate-700">{{ $service->description }}</p>
        @endif

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Price</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $service->priceLabel() }}</p>
            <p class="mt-2 text-sm text-slate-600">
                @if ($is_quote)
                    The price depends on your request. We'll review your details and tell you the price before you pay anything.
                @elseif ($service->isConsultation())
                    Paid when you book your time.
                @else
                    You won't be charged when you send this request. We'll contact you with the next steps, including how to pay.
                @endif
            </p>
        </div>

        @if ($service->isConsultation())
            {{-- Consultations are booked into a time slot, not requested. --}}
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
                <p class="text-lg font-semibold text-slate-900">Book online</p>
                <p class="mt-2 text-slate-600">Choose a date and time that suits you and pay securely. We'll email your confirmation.</p>
                <x-public.button :href="route('book-consultation')" class="mt-4">Book a consultation</x-public.button>
            </div>
        @elseif (! $service->acceptsRequests())
            {{-- Instant services (result checker PINs) are sold through the result checker store. --}}
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
                <p class="text-lg font-semibold text-slate-900">Available from our Result Checker page</p>
                <p class="mt-2 text-slate-600">Buy online and get your PIN by email as soon as your payment is confirmed.</p>
                <x-public.button :href="route('result-checker')" class="mt-4">Go to Result Checker</x-public.button>
            </div>
        @else
            <section class="mt-10" aria-labelledby="request-heading">
                <h2 id="request-heading" class="text-2xl font-bold text-slate-900">Request this service</h2>
                <p class="mt-2 text-slate-600">
                    Fields marked <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required.
                    You don't need an account.
                </p>

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

                <form
                    method="POST"
                    action="{{ route('services.request', ['category' => $category, 'service' => $service]) }}"
                    enctype="multipart/form-data"
                    class="mt-6 space-y-8"
                >
                    @csrf

                    <fieldset class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <legend class="px-1 text-lg font-semibold text-slate-900">Your contact details</legend>

                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-800">Full name <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="name" name="name" type="text" required maxlength="255" autocomplete="name"
                                value="{{ old('name', auth()->user()?->name) }}"
                                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                                class="{{ $input_classes }} mt-1">
                            @error('name') <p id="name-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-slate-800">Phone number <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel"
                                value="{{ old('phone') }}"
                                aria-describedby="phone-help @error('phone') phone-error @enderror"
                                @error('phone') aria-invalid="true" @enderror
                                class="{{ $input_classes }} mt-1">
                            <p id="phone-help" class="mt-1 text-sm text-slate-500">Preferably your WhatsApp number.</p>
                            @error('phone') <p id="phone-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-800">Email address <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="email" name="email" type="email" required maxlength="255" autocomplete="email"
                                value="{{ old('email', auth()->user()?->email) }}"
                                aria-describedby="email-help @error('email') email-error @enderror"
                                @error('email') aria-invalid="true" @enderror
                                class="{{ $input_classes }} mt-1">
                            <p id="email-help" class="mt-1 text-sm text-slate-500">We'll send your reference number here.</p>
                            @error('email') <p id="email-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    @if (count($fields) > 0)
                        <fieldset class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <legend class="px-1 text-lg font-semibold text-slate-900">About your request</legend>

                            @foreach ($fields as $field)
                                <x-public.request-field :field="$field" :upload_rules="$upload_rules" />
                            @endforeach
                        </fieldset>
                    @endif

                    <div>
                        <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                            Send request
                        </button>
                        <p class="mt-3 text-sm text-slate-600">No payment is taken when you send this form.</p>
                    </div>
                </form>
            </section>
        @endif
    </section>
</x-public-layout>
