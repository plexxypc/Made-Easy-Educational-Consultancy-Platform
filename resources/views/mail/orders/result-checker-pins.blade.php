<x-mail::message>
Hello {{ $order->customer_name }},

@if ($pins->count() === 1)
Here is your **{{ $order->service->name }}** PIN for order {{ $order->display_reference }}.
@else
Here are your **{{ $pins->count() }} {{ $order->service->name }}** PINs for order {{ $order->display_reference }}.
@endif

<x-mail::table>
| # | PIN | Serial number |
| :-- | :-- | :-- |
@foreach ($pins as $pin)
| {{ $loop->iteration }} | **{{ $pin->pin }}** | {{ $pin->serial_number ?? '—' }} |
@endforeach
</x-mail::table>

@if ($still_owed > 0)
**{{ $still_owed }} more {{ $still_owed === 1 ? 'PIN is' : 'PINs are' }} on the way.** Our team will send {{ $still_owed === 1 ? 'it' : 'them' }} to you shortly; you don't need to do anything.
@endif

Keep your PIN private: anyone who has it can use it. You can see your PINs again on [your request page]({{ $track_url }}).

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Thanks,<br>
{{ $contact['business_name'] }}
</x-mail::message>
