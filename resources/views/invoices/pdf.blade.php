<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number() }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #27272a; margin: 0; padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header .left { display: table-cell; vertical-align: top; }
        .header .right { display: table-cell; vertical-align: top; text-align: right; }
        .site-name { font-size: 20px; font-weight: bold; color: #18181b; }
        .invoice-number { font-size: 16px; font-weight: bold; color: #18181b; }
        .muted { color: #71717a; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 30px; }
        table.items th { text-align: left; border-bottom: 2px solid #e4e4e7; padding: 8px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #71717a; }
        table.items td { padding: 12px 0; border-bottom: 1px solid #f4f4f5; }
        table.items .amount { text-align: right; }
        .total-row td { border-bottom: none; padding-top: 16px; font-size: 15px; font-weight: bold; color: #18181b; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-refunded { background: #f4f4f5; color: #52525b; }
        .footer { margin-top: 60px; font-size: 10px; color: #a1a1aa; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <div class="site-name">{{ $siteName }}</div>
        </div>
        <div class="right">
            <div class="invoice-number">{{ $invoice->number() }}</div>
            <div class="muted">{{ $invoice->issued_at->format('Y-m-d') }}</div>
        </div>
    </div>

    <div class="muted">Billed to</div>
    <div>{{ $invoice->user?->username ?? $invoice->user?->name ?? 'Guest' }}</div>
    <div class="muted">{{ $invoice->user?->email ?? '' }}</div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Status</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $payment->description() }}</td>
                <td>
                    <span class="status status-{{ $payment->status === 'refunded' ? 'refunded' : 'paid' }}">
                        {{ ucfirst($payment->status) }}
                    </span>
                </td>
                <td class="amount">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="amount">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        {{ $siteName }} &middot; Paid via {{ ucfirst($payment->gateway) }}
    </div>
</body>
</html>
