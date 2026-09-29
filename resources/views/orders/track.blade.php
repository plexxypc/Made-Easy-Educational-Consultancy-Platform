@php($input_classes = 'block min-h-[48px] w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout title="Track your request" description="Check the progress of your Made Easy request with your reference number.">
    <section class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Track your request</h1>
        <p class="mt-3 text-lg text-slate-700">
            Enter the reference number from your confirmation email and the email address or phone number you gave us.
        </p>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-4 text-brand-900" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('orders.track.lookup') }}" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            <div>
                <label for="reference" class="block text-sm font-semibold text-slate-800">Reference number</label>
                <input id="reference" name="reference" type="text" required maxlength="20" inputmode="numeric" autocomplete="off"
                    placeholder="e.g. 1024"
                    value="{{ old('reference') }}"
                    @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror
                    class="{{ $input_classes }} mt-1">
                @error('reference') <p id="reference-error" class="mt-1 text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="contact" class="block text-sm font-semibold text-slate-800">Email or phone number</label>
                <input id="contact" name="contact" type="text" required maxlength="255" autocomplete="email"
                    aria-describedby="contact-help @error('contact') contact-error @enderror"
                    @error('contact') aria-invalid="true" @enderror
                    class="{{ $input_classes }} mt-1">
                <p id="contact-help" class="mt-1 text-sm text-slate-500">The same one you used on the request form.</p>
                @error('contact') <p id="contact-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">
                Find my request
            </button>
        </form>

        <p class="mt-6 text-sm text-slate-600">
            @auth
                You're signed in: see all your orders on <a href="{{ route('my-requests') }}" class="font-semibold text-brand-700 underline">My Requests</a>.
            @else
                Have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-700 underline">Log in</a> to see all your requests.
            @endauth
            Can't find your reference? <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a>.
        </p>
    </section>
</x-public-layout>
