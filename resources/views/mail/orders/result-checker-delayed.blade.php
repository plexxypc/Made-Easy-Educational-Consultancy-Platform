<x-mail::message>
Hello {{ $order->customer_name }},

Thank you, your payment for **{{ $order->serviceLabel() }}** (order {{ $order->display_reference }}) is confirmed.

@if ($is_partial)
We've sent you the PINs we had ready, but **{{ $delayed }} {{ $delayed === 1 ? 'PIN is' : 'PINs are' }} delayed** because we've just run out of stock.
@else
Your {{ $delayed === 1 ? 'PIN is' : 'PINs are' }} **delayed** because we've just run out of stock.
@endif

Our team has been alerted and will email {{ $delayed === 1 ? 'it' : 'them' }} to you as soon as possible, usually within working hours. You don't need to do anything or pay again.

<x-mail::button :url="$track_url">
View your order
</x-mail::button>

If you need your result urgently, contact us and quote {{ $order->display_reference }}.

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Sorry for the wait,<br>
{{ $contact['business_name'] }}
</x-mail::message>
