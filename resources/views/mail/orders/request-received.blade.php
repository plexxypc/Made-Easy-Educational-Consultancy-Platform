<x-mail::message>
Hello {{ $customer_name }},

Your request has been received.

**Reference: {{ $reference }}**<br>
**Service:** {{ $service_name }}<br>
**Price:** {{ $price_label }}

@if ($is_quote)
This service is priced per request. We'll review your details and tell you the price before you pay anything.
@else
No payment has been taken yet. You can pay securely online from your request page.
@endif

Please quote your reference ({{ $reference }}) whenever you contact us.

<x-mail::button :url="$track_url">
Track your request
</x-mail::button>

To check progress, enter your reference and the email or phone number you gave us.

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Thanks,<br>
{{ $contact['business_name'] }}
</x-mail::message>
