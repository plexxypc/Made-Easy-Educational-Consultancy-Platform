<x-mail::message>
Hello {{ $order->customer_name }},

@switch ($kind)
@case (\App\Mail\ConsultationBookingMail::CONFIRMED)
Thank you, your payment is confirmed and your **{{ $order->service->name }}** is booked.
@break
@case (\App\Mail\ConsultationBookingMail::NEEDS_NEW_TIME)
Thank you, your payment is confirmed. Unfortunately the time you chose is **no longer available** (it was taken while your payment was being completed).

Our team has been alerted and will contact you shortly to arrange a new time, or a refund if you prefer. You don't need to pay again.
@break
@case (\App\Mail\ConsultationBookingMail::RESCHEDULED)
Your consultation has been **moved to a new time**.

@if ($previous_slot_label)
Previously: ~~{{ $previous_slot_label }}~~
@endif
@break
@case (\App\Mail\ConsultationBookingMail::CANCELLED)
@case (\App\Mail\ConsultationBookingMail::SLOT_CANCELLED)
We're sorry: your consultation has been **cancelled**{{ $kind === \App\Mail\ConsultationBookingMail::SLOT_CANCELLED ? ' because this consultation time is no longer available' : '' }}.

@if (filled($booking->cancellation_reason))
**Reason:** {{ $booking->cancellation_reason }}
@endif

@if ($was_paid)
Our team will contact you to arrange a new time or a refund. You can also reply to this email or call us.
@else
No payment was taken. You're welcome to [book another time]({{ $book_url }}).
@endif
@break
@case (\App\Mail\ConsultationBookingMail::MEETING_LINK)
Here is the link to join your consultation.
@break
@endswitch

@unless (in_array($kind, [\App\Mail\ConsultationBookingMail::CANCELLED, \App\Mail\ConsultationBookingMail::SLOT_CANCELLED], true))
<x-mail::table>
| | |
| :-- | :-- |
| **{{ $kind === \App\Mail\ConsultationBookingMail::NEEDS_NEW_TIME ? 'Time you chose' : 'Date' }}** | {{ $slot->dateLabel() }} |
| **Time** | {{ $slot->timeLabel() }} |
| **Type** | {{ $slot->consultation_type->getLabel() }} |
@if ($slot->consultation_type->needsMeetingLink() && $kind !== \App\Mail\ConsultationBookingMail::NEEDS_NEW_TIME)
| **Meeting link** | {!! filled($booking->meeting_link) ? '['.e($booking->meeting_link).']('.e($booking->meeting_link).')' : '**[To be added by our team]**' !!} |
@endif
| **Reference** | {{ $order->display_reference }} |
</x-mail::table>

@if ($kind !== \App\Mail\ConsultationBookingMail::NEEDS_NEW_TIME)
@if ($slot->consultation_type->needsMeetingLink() && filled($booking->meeting_link))
Join using the meeting link above at the time booked.
@elseif ($slot->consultation_type->needsMeetingLink())
{{ $slot->consultation_type->customerInstructions() }} It will also appear on your booking page.
@else
{{ $slot->consultation_type->customerInstructions() }}
@endif
@endif
@endunless

<x-mail::button :url="$track_url">
View your booking
</x-mail::button>

Need to change something? Contact us and quote {{ $order->display_reference }}.

**Phone / WhatsApp:** {{ $contact['phone'] }}<br>
**Email:** {{ $contact['email'] }}

Thanks,<br>
{{ $contact['business_name'] }}
</x-mail::message>
