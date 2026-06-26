<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 14px 18px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: {{ $colors['text'] }}; line-height: 1.45; }
        table { border-collapse: collapse; }
        .page { width: 100%; background: #ffffff; }

        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: {{ $colors['navy'] }}; padding-bottom: 8px; }
        .name-accent { font-size: 12px; font-weight: bold; color: {{ $colors['accent'] }}; padding-bottom: 4px; }
        .info-text { font-size: 9px; color: {{ $colors['text'] }}; padding-bottom: 2px; }
        .info-row td { padding: 3px 0; font-size: 9px; border-bottom: 1px solid #f3f4f6; }
        .info-row .lbl { color: {{ $colors['text_muted'] }}; width: 52%; }
        .info-row .val { font-weight: bold; color: #1f2937; text-align: right; }

        .items-table { width: 100%; border: 1px solid {{ $colors['border'] }}; }
        .items-table th { background: {{ $colors['table_head'] }}; color: #ffffff; padding: 9px 8px; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .items-table td { padding: 9px 8px; font-size: 9px; border-bottom: 1px solid #f3f4f6; color: {{ $colors['text'] }}; }
        .items-table .desc { font-weight: bold; color: #1f2937; }
        .items-table .num { text-align: right; }
        .items-table .cnt { text-align: center; color: {{ $colors['text_muted'] }}; }

        .totals td { padding: 4px 0; font-size: 9px; }
        .totals .lbl { color: {{ $colors['text_muted'] }}; }
        .totals .val { text-align: right; font-weight: bold; color: #1f2937; }
        .totals .sub-lbl { font-weight: bold; text-transform: uppercase; color: {{ $colors['navy'] }}; }
        .grand td { background: {{ $colors['table_head'] }}; color: #ffffff; padding: 10px 14px; font-size: 10px; font-weight: bold; }
        .grand-amt { text-align: right; font-size: 13px; }

        .bank dt { font-size: 8px; color: {{ $colors['text_muted'] }}; padding-bottom: 1px; }
        .bank dd { font-size: 9px; font-weight: bold; color: #1f2937; padding-bottom: 6px; }
        .terms-body { font-size: 8px; color: {{ $colors['text_muted'] }}; line-height: 1.55; max-width: 420px; }
        .due-grand td { background: #f0f9ff; color: {{ $colors['navy'] }}; padding: 10px 14px; font-size: 10px; font-weight: bold; border: 2px solid {{ $colors['navy'] }}; }
        .signature-name { font-size: 11px; font-style: italic; color: #1f2937; padding-bottom: 4px; }
        .signature-line { border-top: 2px solid #e5e7eb; padding-top: 6px; margin-top: 28px; width: 160px; }
        .signature-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: {{ $colors['text_muted'] }}; }
    </style>
</head>
<body>
@php
    $billing = $invoice->billing_details ?? [];
    $gst = $invoice->gst_details ?? [];
    $name = $billing['name'] ?? ($invoice->user->name ?? 'Customer');
    $phone = $billing['phone'] ?? ($invoice->user->phone ?? '');
    $email = $billing['email'] ?? ($invoice->user->email ?? '');
    $companyName = $billing['company'] ?? ($invoice->user->company_name ?? '');
    $addressParts = array_filter([
        $billing['address'] ?? ($invoice->user->address ?? ''),
        $billing['city'] ?? ($invoice->user->city ?? ''),
        $billing['state'] ?? ($invoice->user->state ?? ''),
        $billing['pincode'] ?? ($invoice->user->pincode ?? ''),
    ]);
    $address = implode(', ', $addressParts);
    $taxRate = $invoice->items->first()?->tax_rate ?? 18;
    $statusVal = $invoice->status->value ?? (string) $invoice->status;
    $hasDue = $statusVal !== 'paid' && $statusVal !== 'cancelled';
    $isPaid = $statusVal === 'paid';
    $dueBalance = $hasDue ? $invoice->total_amount : 0;
    $daysOverdue = null;
    $daysUntilDue = null;
    $isOverdue = false;
    if ($hasDue && $invoice->due_date) {
        $diffDays = (int) now()->startOfDay()->diffInDays($invoice->due_date->startOfDay(), false);
        $isOverdue = $statusVal === 'overdue' || $diffDays < 0;
        if ($isOverdue) {
            $daysOverdue = abs($diffDays);
        } elseif ($diffDays >= 0) {
            $daysUntilDue = $diffDays;
        }
    }
    $currencySymbol = match ($currency_code ?? 'INR') {
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => $currency_code.' ',
    };
    $currency = $currencySymbol;
@endphp

<table class="page" cellpadding="0" cellspacing="0" width="100%">
    {{-- Header with wavy gradient --}}
    <tr>
        <td style="padding:0;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding: 18px 24px 8px; background-color: {{ $colors['blue'] }};">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="62%" style="vertical-align: middle;">
                                    <table cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="58" style="background:#ffffff; text-align:center; padding:8px 6px; vertical-align:middle;">
                                                @if(!empty($logo_file))
                                                <img src="{{ $logo_file }}" alt="Logo" style="max-width:48px; max-height:48px; object-fit:contain;" />
                                                @else
                                                <div style="font-size:18px; font-weight:bold; color:{{ $colors['blue'] }}; line-height:1;">{{ $company['initials'] }}</div>
                                                <div style="font-size:6px; font-weight:bold; color:{{ $colors['teal'] }}; letter-spacing:1px; text-transform:uppercase; margin-top:3px;">SoftKatta</div>
                                                @endif
                                            </td>
                                            <td style="padding-left:12px; vertical-align:middle;">
                                                <div style="font-size:10px; font-weight:bold; color:#ffffff; text-transform:uppercase; line-height:1.3;">{{ $company['name'] }}</div>
                                                <div style="font-size:8px; color:#ccfbf1; margin-top:3px;">{{ $company['tagline'] }}</div>
                                                <div style="font-size:7px; color:#e0f2fe; margin-top:3px;">{{ $company['address'] }}</div>
                                                <div style="font-size:7px; color:#e0f2fe; margin-top:2px;">{{ $company['phone'] }} &middot; {{ $company['email'] }}</div>
                                                <div style="font-size:7px; color:#e0f2fe;">{{ str_replace('https://', '', $company['website']) }}</div>
                                                <div style="font-size:7px; color:#ffffff; font-weight:bold; margin-top:3px;">GSTIN: {{ $gst['gst_number'] ?? $company['gst_number'] }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="38%" align="right" style="vertical-align: middle;">
                                    <div style="font-size:32px; font-weight:bold; color:#ffffff; text-transform:uppercase; letter-spacing:-0.5px; line-height:1;">Invoice</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0; line-height:0; font-size:0;">
                        <svg width="100%" height="32" viewBox="0 0 1440 200" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="hdrGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:{{ $colors['teal'] }}"/>
                                    <stop offset="50%" style="stop-color:{{ $colors['aqua'] }}"/>
                                    <stop offset="100%" style="stop-color:{{ $colors['blue'] }}"/>
                                </linearGradient>
                            </defs>
                            <path fill="url(#hdrGrad)" d="M0,0 H1440 V80 C1240,120 1040,40 820,80 C600,120 400,40 0,100 V0 Z"/>
                            <path fill="#ffffff" d="M0,55 C220,95 420,15 640,50 C860,85 1080,25 1440,65 L1440,200 L0,200 Z"/>
                        </svg>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Two-column info --}}
    <tr>
        <td style="padding: 14px 24px 18px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%" style="vertical-align: top; padding-right: 14px;">
                        <div class="section-title">Invoice To</div>
                        <div class="name-accent">{{ $name }}</div>
                        @if($companyName)<div class="info-text">{{ $companyName }}</div>@endif
                        @if($address)<div class="info-text">{{ $address }}</div>@endif
                        @if($phone)<div class="info-text">{{ $phone }}</div>@endif
                        @if($email)<div class="info-text">{{ $email }}</div>@endif
                        @if(!empty($gst['customer_gst']))<div class="info-text">GSTIN: {{ $gst['customer_gst'] }}</div>@endif
                    </td>
                    <td width="50%" style="vertical-align: top; padding-left: 14px;">
                        <div class="section-title">Invoice No. {{ $invoice->invoice_number }}</div>
                        <table width="100%" cellpadding="0" cellspacing="0" class="info-row">
                            <tr><td class="lbl">Invoice Date</td><td class="val">{{ $invoice->created_at->format('d M Y') }}</td></tr>
                            @if($hasDue)
                            <tr><td class="lbl">Due Date</td><td class="val">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td></tr>
                            <tr><td class="lbl">Due Balance</td><td class="val" style="color:{{ $colors['navy'] }};">{{ $currency }}{{ number_format($dueBalance, 2) }}</td></tr>
                            @if($isOverdue && $daysOverdue !== null)
                            <tr><td class="lbl">Days Overdue</td><td class="val" style="color:#dc2626;">{{ $daysOverdue }} days</td></tr>
                            @elseif($daysUntilDue !== null)
                            <tr><td class="lbl">Days Until Due</td><td class="val">{{ $daysUntilDue }} days</td></tr>
                            @endif
                            @endif
                            @if($isPaid && $invoice->paid_at)
                            <tr><td class="lbl">Paid Date</td><td class="val">{{ $invoice->paid_at->format('d M Y') }}</td></tr>
                            <tr><td class="lbl">Amount Paid</td><td class="val" style="color:#047857;">{{ $currency }}{{ number_format($invoice->total_amount, 2) }}</td></tr>
                            @endif
                            <tr><td class="lbl">Status</td><td class="val">{{ ucfirst($statusVal) }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Line items --}}
    <tr>
        <td style="padding: 0 24px 16px;">
            <table class="items-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th width="8%" style="text-align:center;">S/L</th>
                        <th width="44%">Product Description</th>
                        <th width="10%" style="text-align:center;">Qty</th>
                        <th width="18%" style="text-align:right;">Unit Price</th>
                        <th width="20%" style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $index => $item)
                    <tr>
                        <td class="cnt">{{ $index + 1 }}</td>
                        <td class="desc">{{ $item->description }}</td>
                        <td class="cnt">{{ $item->quantity }}</td>
                        <td class="num">{{ $currency }}{{ number_format($item->unit_price, 2) }}</td>
                        <td class="num desc">{{ $currency }}{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>

    {{-- Totals --}}
    <tr>
        <td style="padding: 0 24px 18px;" align="right">
            <table width="220" cellpadding="0" cellspacing="0" class="totals" align="right">
                <tr>
                    <td class="sub-lbl" style="border-bottom:1px solid #f3f4f6; padding-bottom:6px;">Sub-Total</td>
                    <td class="val" style="border-bottom:1px solid #f3f4f6; padding-bottom:6px;">{{ $currency }}{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->cgst > 0)
                <tr><td class="lbl">CGST ({{ number_format($taxRate / 2, 0) }}%)</td><td class="val">{{ $currency }}{{ number_format($invoice->cgst, 2) }}</td></tr>
                <tr><td class="lbl">SGST ({{ number_format($taxRate / 2, 0) }}%)</td><td class="val">{{ $currency }}{{ number_format($invoice->sgst, 2) }}</td></tr>
                @endif
                @if($invoice->igst > 0)
                <tr><td class="lbl">GST ({{ number_format($taxRate, 0) }}%)</td><td class="val">{{ $currency }}{{ number_format($invoice->igst, 2) }}</td></tr>
                @endif
                @if($invoice->cgst <= 0 && $invoice->igst <= 0)
                <tr><td class="lbl">GST</td><td class="val">{{ $currency }}0.00</td></tr>
                @endif
                <tr><td colspan="2" style="height:8px;"></td></tr>
                <tr class="grand">
                    <td width="50%">TOTAL</td>
                    <td width="50%" class="grand-amt">{{ $currency }}{{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
                @if($hasDue)
                <tr><td colspan="2" style="height:6px;"></td></tr>
                <tr class="due-grand">
                    <td width="50%">AMOUNT DUE</td>
                    <td width="50%" class="grand-amt">{{ $currency }}{{ number_format($dueBalance, 2) }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>

    {{-- Payment QR + Terms --}}
    <tr>
        <td style="padding: 0 24px 10px;">
            @if($hasDue && !empty($payment_qr_uri))
            <div style="margin-bottom:14px;">
                <div class="section-title">Scan to Pay</div>
                <div style="display:inline-block; text-align:center; border:1px solid {{ $colors['border'] }}; padding:8px; background:#ffffff;">
                    <img src="{{ $payment_qr_uri }}" alt="Scan to pay" style="width:120px; height:120px;" />
                    <div style="font-size:8px; font-weight:bold; color:{{ $colors['navy'] }}; text-transform:uppercase; margin-top:4px;">Scan to Pay</div>
                    <div style="font-size:7px; color:{{ $colors['text_muted'] }};">UPI QR · {{ $company['name'] }}</div>
                </div>
            </div>
            @endif
            <div class="section-title">Terms</div>
            <div class="terms-body">{{ $terms }}</div>
        </td>
    </tr>

    @if(!empty($company['signatory']) || !empty($signature_file))
    <tr>
        <td style="padding: 8px 24px 16px;" align="right">
            @if(!empty($signature_file))
            <img src="{{ $signature_file }}" alt="Signature" style="max-height:56px; max-width:160px; object-fit:contain; margin-bottom:4px;" />
            @endif
            @if(!empty($company['signatory']))
            <div class="signature-name">{{ $company['signatory'] }}</div>
            @endif
            <div class="signature-line" align="right">
                <div class="signature-label">Authorized Signature</div>
            </div>
        </td>
    </tr>
    @endif

    {{-- Footer wave (full width) --}}
    <tr>
        <td style="padding:0; line-height:0; font-size:0; position:relative;">
            <div style="position:absolute; right:24px; bottom:12px; font-size:48px; font-weight:bold; color:#ffffff44; z-index:1;">{{ $company['initials'] }}</div>
            <svg width="100%" height="48" viewBox="0 0 1440 112" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="footerWave" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:{{ $colors['teal'] }}"/>
                        <stop offset="45%" style="stop-color:{{ $colors['aqua'] }}"/>
                        <stop offset="100%" style="stop-color:{{ $colors['blue'] }}"/>
                    </linearGradient>
                </defs>
                <path fill="url(#footerWave)" d="M0,48 C240,8 480,88 720,52 C960,16 1200,76 1440,40 L1440,112 L0,112 Z"/>
            </svg>
        </td>
    </tr>
</table>
</body>
</html>
