@php($contact = config('madeeasy.contact'))

<footer class="mt-16 bg-ink text-white [&_:focus-visible]:outline-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
        <div>
            <p class="font-display text-lg font-bold">{{ $contact['business_name'] }}</p>
            <p class="mt-2 text-sm text-white/70">Making your educational journey easier.</p>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-white/60">Contact</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="font-medium text-accent hover:underline">{{ $contact['phone'] }}</a></li>
                <li><a href="mailto:{{ $contact['email'] }}" class="font-medium text-accent hover:underline">{{ $contact['email'] }}</a></li>
                <li class="text-white/70">{{ $contact['address'] }}</li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-white/60">Explore</p>
            <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                @foreach (\App\Support\PublicNavigation::items(request()) as $item)
                    <li><a href="{{ $item['url'] }}" class="text-white/85 hover:text-accent hover:underline">{{ $item['label'] }}</a></li>
                @endforeach
                <li><a href="{{ route('orders.track') }}" class="text-white/85 hover:text-accent hover:underline">Track your request</a></li>
                <li><a href="{{ route('maths-tutoring') }}" class="text-white/85 hover:text-accent hover:underline">Maths tutorials</a></li>
                <li><a href="{{ route('faq') }}" class="text-white/85 hover:text-accent hover:underline">FAQ</a></li>
                <li><a href="{{ route('testimonials') }}" class="text-white/85 hover:text-accent hover:underline">Testimonials</a></li>
            </ul>
        </div>
    </div>
    {{-- Bottom padding keeps the floating WhatsApp button from covering this line. --}}
    <div class="border-t border-white/10 pb-24 pt-4 text-center text-xs text-white/60 sm:pb-4">
        &copy; {{ now()->year }} {{ $contact['business_name'] }}
    </div>
</footer>
