@php($contact = config('madeeasy.contact'))
{{-- Self-contained receipt (inline CSS, no external assets) so the downloaded file looks the same offline and prints cleanly. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Receipt {{ $receipt->number }} | {{ $contact['business_name'] }}</title>
    <style>
        body { margin: 0; background: #f1f5f9; color: #0f172a; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .receipt { max-width: 640px; margin: 24px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px; }
        .head { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; border-bottom: 2px solid #1d4ed8; padding-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 800; }
        .muted { color: #64748b; font-size: 14px; }
        h1 { margin: 0; font-size: 24px; }
        .status { display: inline-block; margin-top: 6px; padding: 2px 12px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 700; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 10px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        th { width: 40%; color: #475569; font-weight: 600; }
        .total td, .total th { font-size: 20px; font-weight: 800; color: #0f172a; border-bottom: 0; }
        .foot { margin-top: 24px; font-size: 14px; color: #475569; }
        .actions { max-width: 640px; margin: 0 auto 24px; display: flex; gap: 12px; flex-wrap: wrap; padding: 0 16px; }
        .actions a { display: inline-block; padding: 12px 20px; border-radius: 12px; background: #1d4ed8; color: #fff; font-weight: 600; text-decoration: none; }
        .actions a.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        @media print { body { background: #fff; } .receipt { border: 0; margin: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <main class="receipt">
        <div class="head">
            <div>
                <div class="brand">{{ $contact['business_name'] }}</div>
                <div class="muted">{{ $contact['address'] }}</div>
                <div class="muted">{{ $contact['phone'] }} · {{ $contact['email'] }}</div>
            </div>
            <div>
                <h1>Payment receipt</h1>
                <div class="muted">{{ $receipt->number }}</div>
                <span class="status">{{ $receipt->payment_status }}</span>
            </div>
        </div>

        <table>
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

        <p class="foot">Thank you for choosing {{ $contact['business_name'] }}. Please quote your order reference whenever you contact us.</p>
    </main>

    <nav class="actions" aria-label="Receipt actions">
        <a href="{{ route('orders.receipt', ['order' => $receipt->order_reference, 'download' => 1]) }}">Download receipt</a>
        <a class="secondary" href="{{ route('orders.track.show', ['order' => $receipt->order_reference]) }}">Back to your request</a>
    </nav>
</body>
</html>
