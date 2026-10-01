<x-mail::message>
Hello {{ $receipt->customer_name }},

Thank you, we've received your payment. Your receipt is attached as a PDF, and the details are below.

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
| **Paid on** | {{ $receipt->paidAtLabel() }} |
</x-mail::table>

<x-mail::button :url="$receipt_url">
Download receipt (PDF)
</x-mail::button>

@if ($is_result_checker)
Your result checker {{ $pin_noun }} will arrive in a separate email and will also be shown on [your request page]({{ $track_url }}).
@elseif ($is_consultation)
Your booking confirmation, with the date, time and type of your consultation, follows in a separate email. It is also shown on [your booking page]({{ $track_url }}).
@else
We'll now start work on your request. You can follow its progress on [your request page]({{ $track_url }}).
@endif

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Thanks,<br>
{{ $contact['business_name'] }}
</x-mail::message>
