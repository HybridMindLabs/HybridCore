<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number() }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #3f3f46; margin: 0; padding: 0; }

        .accent-bar { height: 6px; background: #18181b; }
        .sheet { padding: 36px 44px 30px; }

        .header { display: table; width: 100%; margin-bottom: 32px; }
        .header .left { display: table-cell; vertical-align: top; width: 60%; }
        .header .right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .site-name { font-size: 19px; font-weight: bold; color: #18181b; letter-spacing: -0.01em; }
        .site-meta { color: #a1a1aa; font-size: 10.5px; margin-top: 3px; }
        .doc-label { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em; color: #a1a1aa; }
        .invoice-number { font-size: 20px; font-weight: bold; color: #18181b; margin-top: 2px; }

        .status { display: inline-block; margin-top: 8px; padding: 4px 11px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-refunded { background: #f4f4f5; color: #52525b; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }

        .divider { border: none; border-top: 1px solid #e4e4e7; margin: 26px 0; }

        .meta-grid { display: table; width: 100%; margin-bottom: 8px; }
        .meta-col { display: table-cell; vertical-align: top; width: 33.33%; padding-right: 16px; }
        .meta-label { font-size: 9.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #a1a1aa; margin-bottom: 4px; }
        .meta-value { font-size: 12.5px; color: #27272a; font-weight: 600; }
        .meta-sub { font-size: 11px; color: #a1a1aa; margin-top: 2px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 28px; }
        table.items th { text-align: left; background: #fafafa; border-top: 1px solid #e4e4e7; border-bottom: 1px solid #e4e4e7; padding: 9px 12px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #71717a; }
        table.items th.amount, table.items td.amount { text-align: right; }
        table.items td { padding: 13px 12px; border-bottom: 1px solid #f4f4f5; font-size: 12.5px; color: #27272a; }
        table.items td.amount { font-weight: 600; }

        .totals { width: 260px; margin-left: auto; margin-top: 4px; }
        .totals .row { display: table; width: 100%; padding: 7px 12px; }
        .totals .row .label { display: table-cell; color: #71717a; font-size: 12px; }
        .totals .row .value { display: table-cell; text-align: right; font-size: 12px; color: #27272a; font-weight: 600; }
        .totals .grand { background: #18181b; border-radius: 4px; margin-top: 6px; }
        .totals .grand .label { color: #fafafa; font-size: 12.5px; font-weight: bold; }
        .totals .grand .value { color: #fafafa; font-size: 16px; font-weight: bold; }

        .footer { margin-top: 50px; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 10px; color: #a1a1aa; text-align: center; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="accent-bar"></div>
    <div class="sheet">
        <div class="header">
            <div class="left">
                <div class="site-name">{{ $siteName }}</div>
                @if($contactEmail !== '')
                    <div class="site-meta">{{ $contactEmail }}</div>
                @endif
            </div>
            <div class="right">
                <div class="doc-label">Invoice</div>
                <div class="invoice-number">{{ $invoice->number() }}</div>
                <span class="status status-{{ in_array($payment->status, ['paid', 'refunded', 'failed'], true) ? $payment->status : 'pending' }}">
                    {{ ucfirst($payment->status) }}
                </span>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-col">
                <div class="meta-label">Billed to</div>
                <div class="meta-value">{{ $invoice->user?->username ?? $invoice->user?->name ?? 'Guest' }}</div>
                <div class="meta-sub">{{ $invoice->user?->email ?? '' }}</div>
            </div>
            <div class="meta-col">
                <div class="meta-label">Date issued</div>
                <div class="meta-value">{{ $invoice->issued_at->format('F j, Y') }}</div>
            </div>
            <div class="meta-col">
                <div class="meta-label">Payment method</div>
                <div class="meta-value">{{ ucfirst($payment->gateway) }}</div>
                <div class="meta-sub">{{ $payment->external_id ?? '' }}</div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $payment->description() }}</td>
                    <td class="amount">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <div class="label">Subtotal</div>
                <div class="value">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</div>
            </div>
            <div class="row grand">
                <div class="label">Total</div>
                <div class="value">{{ strtoupper($invoice->currency) }} {{ number_format($invoice->amount / 100, 2) }}</div>
            </div>
        </div>

        <div class="footer">
            {{ $siteName }} &middot; This is an automatically generated receipt for a purchase made on {{ $siteName }}.
            @if($contactEmail !== '')
                <br>Questions? Contact {{ $contactEmail }}.
            @endif
        </div>
    </div>
</body>
</html>
