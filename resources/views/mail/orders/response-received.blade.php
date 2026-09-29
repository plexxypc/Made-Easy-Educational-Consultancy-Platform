<x-mail::message>
Hello {{ $customer_name }},

Thank you. We've received your response for **{{ $reference }}** ({{ $service_name }}), and our team will continue with your request.

@if ($note)
**Your message:**

{{ $note }}
@endif

@if (count($document_names) > 0)
**Documents received:**

@foreach ($document_names as $document_name)
- {{ $document_name }}
@endforeach
@endif

Your request is now **{{ $status_label }}**.

<x-mail::button :url="$track_url">
View your request
</x-mail::button>

If you didn't send this, please contact us straight away.

Thanks,<br>
{{ config('madeeasy.contact.business_name') }}
</x-mail::message>
