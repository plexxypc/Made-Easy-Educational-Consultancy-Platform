<x-mail::message>
Hello {{ $customer_name }},

The status of your order **#{{ $order_number }}** ({{ $service_name }}) is now **{{ $status_label }}**.

@if ($note)
**Message from our team:**

{{ $note }}
@endif

@if ($needs_action)
Please respond on your request page: you can send a message and upload documents there.
@endif

<x-mail::button :url="$track_url">
{{ $needs_action ? 'Respond to this request' : 'View your request' }}
</x-mail::button>

You may be asked for the email or phone number you used when you placed the order.

If you have any questions, just reply to this email or contact us on WhatsApp.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
