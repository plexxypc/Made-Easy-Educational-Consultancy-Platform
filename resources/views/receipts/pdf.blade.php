{{-- Receipt PDF (App\Actions\Receipts\RenderReceiptPdf). dompdf supports only simple CSS: tables for layout, no flexbox, no external assets. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->number }}</title>
    <style>
        @page { margin: 36px 42px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.5; color: #0f172a; }
        .head { width: 100%; border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; }
        .head td { vertical-align: top; }
        .brand { font-size: 16px; font-weight: bold; }
        .muted { color: #64748b; font-size: 10px; }
        h1 { margin: 0; font-size: 18px; text-align: right; }
        .number { text-align: right; color: #64748b; }
        .status { text-align: right; margin-top: 4px; }
        .status span { background: #dcfce7; color: #166534; font-weight: bold; padding: 2px 8px; border-radius: 8px; font-size: 10px; }
        .details { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .details th, .details td { text-align: left; padding: 8px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .details th { width: 38%; color: #475569; font-weight: normal; }
        .total th, .total td { font-size: 14px; font-weight: bold; color: #0f172a; border-bottom: 0; padding-top: 12px; }
        .foot { margin-top: 24px; color: #475569; font-size: 10px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">{{ $contact['business_name'] }}</div>
                <div class="muted">{{ $contact['address'] }}</div>
                <div class="muted">{{ $contact['phone'] }} · {{ $contact['email'] }}</div>
            </td>
            <td>
                <h1>Payment receipt</h1>
                <div class="number">{{ $receipt->number }}</div>
                <div class="status"><span>{{ $receipt->payment_status }}</span></div>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr><th>Receipt number</th><td>{{ $receipt->number }}</td></tr>
        <tr><th>Paid on</th><td>{{ $receipt->paid_at->format('j F Y, g:ia') }}</td></tr>
        <tr><th>Order reference</th><td>{{ $receipt->displayOrderReference() }}</td></tr>
        <tr><th>Payment reference</th><td>{{ $receipt->payment_reference }}</td></tr>
        <tr><th>Customer</th><td>{{ $receipt->customer_name }}</td></tr>
        @if ($receipt->customer_email)
            <tr><th>Email</th><td>{{ $receipt->customer_email }}</td></tr>
        @endif
        @if ($receipt->customer_phone)
            <tr><th>Phone</th><td>{{ $receipt->customer_phone }}</td></tr>
        @endif
        <tr><th>Service</th><td>{{ $receipt->service_name }}</td></tr>
        <tr><th>Payment method</th><td>Paystack</td></tr>
        <tr class="total"><th>Amount paid</th><td>{{ $receipt->amount_label }}</td></tr>
    </table>

    <p class="foot">
        Thank you for choosing {{ $contact['business_name'] }}. Please quote your order reference
        ({{ $receipt->displayOrderReference() }}) whenever you contact us.
    </p>
</body>
</html>
