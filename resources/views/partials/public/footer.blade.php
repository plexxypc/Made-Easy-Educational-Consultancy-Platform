@php($contact = config('madeeasy.contact'))

<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
        <div>
            <p class="text-base font-bold text-slate-900">{{ $contact['business_name'] }}</p>
            <p class="mt-2 text-sm text-slate-600">Making your educational journey easier.</p>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Contact</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="font-medium text-brand-700 hover:underline">{{ $contact['phone'] }}</a></li>
                <li><a href="mailto:{{ $contact['email'] }}" class="font-medium text-brand-700 hover:underline">{{ $contact['email'] }}</a></li>
                <li class="text-slate-600">{{ $contact['address'] }}</li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Explore</p>
            <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                @foreach (\App\Support\PublicNavigation::items(request()) as $item)
                    <li><a href="{{ $item['url'] }}" class="text-slate-700 hover:text-brand-700 hover:underline">{{ $item['label'] }}</a></li>
                @endforeach
                <li><a href="{{ route('orders.track') }}" class="text-slate-700 hover:text-brand-700 hover:underline">Track your request</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-slate-100 py-4 text-center text-xs text-slate-500">
        &copy; {{ now()->year }} {{ $contact['business_name'] }}
    </div>
</footer>
