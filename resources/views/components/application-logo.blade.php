{{-- Made Easy logo (public/images/logo.png). Size it from the caller with h-* and w-auto; the aspect ratio is kept. --}}
<img src="{{ asset('images/logo.png') }}" alt="{{ config('madeeasy.contact.business_name') }}" width="800" height="627" {{ $attributes }}>
