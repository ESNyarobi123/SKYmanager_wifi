<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; background: #fff; }

        .page { padding: 40px; max-width: 800px; margin: 0 auto; }

        /* ── Header ── */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 3px solid #0369a1; padding-bottom: 24px; }
        .brand-name { font-size: 28px; font-weight: 700; color: #0369a1; letter-spacing: -0.5px; }
        .brand-tagline { font-size: 11px; color: #64748b; margin-top: 2px; }
        .invoice-badge { background: #0369a1; color: #fff; padding: 6px 16px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
        .invoice-number { font-size: 18px; font-weight: 700; color: #0369a1; margin-top: 4px; text-align: right; }
        .invoice-date { font-size: 11px; color: #64748b; margin-top: 2px; text-align: right; }

        /* ── Parties ── */
        .parties { display: flex; justify-content: space-between; margin-bottom: 28px; gap: 24px; }
        .party-box { flex: 1; }
        .party-label { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .party-name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .party-detail { font-size: 11px; color: #475569; margin-top: 2px; }

        /* ── Info boxes ── */
        .info-grid { display: flex; gap: 12px; margin-bottom: 24px; }
        .info-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        .info-box-label { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-box-value { font-size: 13px; font-weight: 600; color: #0f172a; margin-top: 3px; }

        /* ── Line items table ── */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table thead tr { background: #0369a1; color: #fff; }
        .items-table thead th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table tbody td { padding: 10px 12px; font-size: 12px; color: #334155; }
        .items-table tbody td:last-child { text-align: right; font-weight: 600; }

        /* ── Totals ── */
        .totals { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 240px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .totals-row.total { background: #0369a1; color: #fff; padding: 10px 12px; border-radius: 6px; border: none; margin-top: 4px; font-weight: 700; font-size: 14px; }

        /* ── Status stamp ── */
        .status-paid { position: absolute; top: 48px; right: 48px; border: 3px solid #16a34a; color: #16a34a; font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 3px; padding: 6px 18px; border-radius: 4px; opacity: 0.35; transform: rotate(-12deg); }

        /* ── Footer ── */
        .footer { margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 16px; text-align: center; font-size: 10px; color: #94a3b8; line-height: 1.6; }
        .footer strong { color: #64748b; }
        .thank-you { font-size: 13px; font-weight: 600; color: #0369a1; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="page" style="position: relative;">

    @if($invoice->isPaid())
        <div class="status-paid">PAID</div>
    @endif

    {{-- ── Header ───────────────────────────────────── --}}
    <div class="header">
        <div>
            <div class="brand-name">SKYmanager</div>
            <div class="brand-tagline">WiFi Billing &amp; Network Management Platform</div>
            <div style="margin-top: 8px; font-size: 11px; color: #64748b;">
                {{ config('app.url') }}<br>
                support@skymanager.co.tz
            </div>
        </div>
        <div style="text-align: right;">
            <div class="invoice-badge">Invoice</div>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            <div class="invoice-date">Issued: {{ $invoice->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</div>
            @if($invoice->due_at)
                <div class="invoice-date">Due: {{ $invoice->due_at->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    {{-- ── Parties ─────────────────────────────────── --}}
    <div class="parties">
        <div class="party-box">
            <div class="party-label">From</div>
            <div class="party-name">SKYmanager Ltd.</div>
            <div class="party-detail">Dar es Salaam, Tanzania</div>
            <div class="party-detail">TIN: {{ config('invoice.company_tin', 'TBD') }}</div>
        </div>
        <div class="party-box" style="text-align: right;">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->customer->name }}</div>
            @if($invoice->customer->company_name)
                <div class="party-detail">{{ $invoice->customer->company_name }}</div>
            @endif
            <div class="party-detail">{{ $invoice->customer->phone }}</div>
            @if($invoice->customer->email)
                <div class="party-detail">{{ $invoice->customer->email }}</div>
            @endif
        </div>
    </div>

    {{-- ── Info Grid ────────────────────────────────── --}}
    <div class="info-grid">
        <div class="info-box">
            <div class="info-box-label">Invoice #</div>
            <div class="info-box-value">{{ $invoice->invoice_number }}</div>
        </div>
        <div class="info-box">
            <div class="info-box-label">Status</div>
            <div class="info-box-value" style="color: {{ $invoice->isPaid() ? '#16a34a' : '#d97706' }};">
                {{ strtoupper($invoice->status) }}
            </div>
        </div>
        <div class="info-box">
            <div class="info-box-label">Router</div>
            <div class="info-box-value">{{ $invoice->subscription?->router?->name ?? '—' }}</div>
        </div>
        <div class="info-box">
            <div class="info-box-label">Period</div>
            <div class="info-box-value">
                @if($invoice->subscription?->expires_at)
                    Until {{ $invoice->subscription->expires_at->format('d M Y') }}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    {{-- ── Line Items ───────────────────────────────── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">Description</th>
                <th>Plan</th>
                <th>Router</th>
                <th>Qty</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>WiFi Service Subscription</strong><br>
                    <span style="font-size: 10px; color: #64748b;">
                        @if($invoice->subscription?->plan)
                            {{ $invoice->subscription->plan->name }} —
                            {{ $invoice->subscription->plan->download_limit }}Mbps ↓ /
                            {{ $invoice->subscription->plan->upload_limit }}Mbps ↑
                        @endif
                    </span>
                </td>
                <td>{{ $invoice->subscription?->plan?->name ?? '—' }}</td>
                <td>{{ $invoice->subscription?->router?->name ?? '—' }}</td>
                <td>1</td>
                <td>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── Totals ───────────────────────────────────── --}}
    <div class="totals">
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</span>
            </div>
            @if((float) $invoice->tax_amount > 0)
                <div class="totals-row">
                    <span>VAT ({{ config('invoice.tax_rate', 0) }}%)</span>
                    <span>{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</span>
                </div>
            @endif
            <div class="totals-row total">
                <span>TOTAL DUE</span>
                <span>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- ── Payment Info ─────────────────────────────── --}}
    @if($invoice->payment)
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="font-size: 11px; font-weight: 600; color: #16a34a; text-transform: uppercase; margin-bottom: 6px;">✓ Payment Received</div>
            <div style="font-size: 11px; color: #166534;">
                Provider: <strong>{{ $invoice->payment->provider }}</strong> &nbsp;|&nbsp;
                Reference: <strong>{{ $invoice->payment->reference ?? $invoice->payment->transaction_id ?? '—' }}</strong> &nbsp;|&nbsp;
                Date: <strong>{{ $invoice->payment->created_at->format('d M Y H:i') }}</strong>
            </div>
        </div>
    @endif

    {{-- ── Notes ───────────────────────────────────── --}}
    @if($invoice->notes)
        <div style="font-size: 11px; color: #64748b; margin-bottom: 16px;">
            <strong>Notes:</strong> {{ $invoice->notes }}
        </div>
    @endif

    {{-- ── Footer ──────────────────────────────────── --}}
    <div class="footer">
        <div class="thank-you">Thank you for choosing SKYmanager!</div>
        <p>This is a computer-generated invoice and does not require a signature.</p>
        <p><strong>SKYmanager WiFi Billing System</strong> &mdash; Empowering ISPs &amp; Hotspot Operators across East Africa</p>
        <p style="margin-top: 6px;">{{ config('app.url') }} &nbsp;|&nbsp; support@skymanager.co.tz</p>
    </div>

</div>
</body>
</html>
