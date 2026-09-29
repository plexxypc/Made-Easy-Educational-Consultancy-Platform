<x-mail::message>
Hello {{ $receipt->customer_name }},

Thank you, we've received your payment. Here is your receipt.

<x-mail::table>
| | |
| :-- | :-- |
| **Receipt number** | {{ $receipt->number }} |
| **Order reference** | {{ $receipt->displayOrderReference() }} |
| **Payment reference** | {{ $receipt->payment_reference }} |
| **Customer** | {{ $receipt->customer_name }} |
@if ($receipt->customer_email)
| **Email** | {{ $receipt->customer_email }} |
@endif
@if ($receipt->customer_phone)
| **Phone** | {{ $receipt->customer_phone }} |
@endif
| **Service** | {{ $receipt->service_name }} |
| **Amount** | {{ $receipt->amount_label }} |
| **Status** | {{ $receipt->payment_status }} |
| **Paid on** | {{ $receipt->paid_at->format('j M Y, g:ia') }} |
</x-mail::table>

<x-mail::button :url="$receipt_url">
View or download receipt
</x-mail::button>

We'll now start work on your request. You can follow its progress on [your request page]({{ $track_url }}).

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Thanks,<br>
{{ $contact['business_name'] }}
</x-mail::message>
