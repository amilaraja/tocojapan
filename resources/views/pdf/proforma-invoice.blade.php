@php
    /** @var \App\Models\ProformaInvoice $invoice */
    /** @var \App\Settings\ProformaSettings $s */
    $money = fn ($v) => number_format((float) $v, 0);
    $date = fn ($d) => $d->format('F j, Y');
    $dest = trim(($v['port'] ?? '').' / '.($v['country'] ?? ''), ' /');
    $unitTitle = trim(($v['year'] ?? '').' '.($v['make'] ?? '').' '.($v['model'] ?? ''));
    $dims = collect([['L', $v['length_cm'] ?? null], ['W', $v['width_cm'] ?? null], ['H', $v['height_cm'] ?? null]])
        ->filter(fn ($d) => $d[1])
        ->map(fn ($d) => '('.$d[0].') '.rtrim(rtrim(number_format($d[1], 1), '0'), '.').' cm')
        ->implode(', ');
    $specs = array_filter([
        'First Registration' => $v['registration'] ?? $v['year'] ?? null,
        'Chassis No' => $v['chassis_number'] ?? null,
        'Model Code' => $v['model_code'] ?? null,
        'Color' => $v['color'] ?? null,
        'Fuel' => $v['fuel'] ?? null,
        'Transmission' => $v['transmission'] ?? null,
        'Engine' => ! empty($v['engine_cc']) ? number_format((int) $v['engine_cc']).' cc' : null,
        'Mileage' => isset($v['mileage_km']) ? number_format((int) $v['mileage_km']).' km' : null,
        'Steering' => $v['steering'] ?? null,
        'Doors' => $v['doors'] ?? null,
        'Passengers' => $v['seats'] ?? null,
        'Dimensions' => trim($dims.(! empty($v['m3']) ? ' — '.number_format((float) $v['m3'], 2).' M3' : ''), ' —'),
    ], fn ($x) => $x !== null && $x !== '');
    // Dimensions get a full-width row; the rest print two per row.
    $dimLine = $specs['Dimensions'] ?? null;
    unset($specs['Dimensions']);
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Proforma Invoice {{ $invoice->invoice_no }}</title>
<style>
    @page { margin: 9mm 11mm 8mm 11mm; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8.6pt; color: #1a1a1a; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .sheet { border: 1px solid #1f2356; }
    .accent { height: 4px; background: #e30613; }
    .head td { padding: 8px 12px; line-height: 1.35; }
    .company-name { font-size: 11pt; font-weight: bold; color: #1f2356; letter-spacing: .3px; }
    .muted { color: #555; }
    .small { font-size: 8.5pt; }
    a { color: #1f2356; text-decoration: none; }
    .titlebar td { padding: 6px 12px; border-top: 1px solid #d9dbe3; border-bottom: 1px solid #d9dbe3; background: #f4f5f7; }
    .doc-title { font-size: 14pt; font-weight: bold; color: #1f2356; letter-spacing: 1.5px; }
    .doc-meta td { padding: 1px 0; }
    .bar td { background: #1f2356; color: #fff; font-weight: bold; font-size: 8.5pt; letter-spacing: 1px; padding: 3px 12px; border-left: 4px solid #e30613; }
    .block td { padding: 4px 12px; line-height: 1.4; }
    .grid td { padding: 3px 12px; border-top: 1px solid #d9dbe3; }
    .grid td + td { border-left: 1px solid #d9dbe3; }
    .label { font-weight: bold; color: #1f2356; }
    .unit-title { font-weight: bold; font-size: 9.5pt; color: #1f2356; padding: 5px 12px 3px; }
    .photo { width: 186px; padding: 2px 6px 6px 12px; }
    .photo img { width: 176px; border: 1px solid #d9dbe3; }
    .specs { padding: 2px 12px 6px 6px; }
    .specs table td { padding: 1px 4px 1px 0; }
    .specs .k { color: #555; width: 96px; white-space: nowrap; }
    .specs .v { font-weight: bold; }
    .stock { font-size: 10pt; font-weight: bold; color: #e30613; padding-bottom: 4px !important; }
    .lines th { background: #e6e7e8; color: #1f2356; font-size: 8pt; text-align: left; padding: 4px 8px; border-top: 1px solid #1f2356; border-bottom: 1px solid #1f2356; }
    .lines td { padding: 3px 8px; border-bottom: 1px solid #eceef2; }
    .lines .num { text-align: right; width: 70px; }
    .lines .qty { text-align: center; width: 50px; }
    .lines .neg { color: #e30613; }
    .lines .total td { border-top: 2px solid #1f2356; border-bottom: none; background: #f4f5f7; font-weight: bold; color: #1f2356; font-size: 9.5pt; padding: 5px 8px; }
    .lines .total .num { color: #e30613; font-size: 11pt; }
    .pay td { padding: 1px 12px; line-height: 1.35; vertical-align: top; }
    .pay .h { font-weight: bold; color: #1f2356; padding-top: 5px; }
    .terms { font-size: 8pt; color: #555; padding: 6px 2px 0; }
    .foot td { padding: 6px 6px 0; vertical-align: middle; }
    .thanks { font-size: 11pt; color: #1f2356; font-weight: bold; }
    .sign { text-align: right; }
    .sign-name { display: inline-block; border-top: 1px solid #1f2356; padding-top: 3px; font-size: 9pt; }
</style>
</head>
<body>
<div class="sheet">
    <div class="accent"></div>

    {{-- Header: logo + company --}}
    <table class="head">
        <tr>
            <td style="width: 42%; vertical-align: middle;">
                @if ($logo)<img src="{{ $logo }}" style="width: 220px;">@endif
            </td>
            <td>
                <div class="company-name">{{ $s->company_name }}</div>
                <div>{{ $s->company_address }}</div>
                <div>TEL: {{ $s->company_tel }}@if ($s->company_fax) &nbsp;&nbsp; FAX: {{ $s->company_fax }}@endif</div>
                <div>Email: <a href="mailto:{{ $s->company_email }}">{{ $s->company_email }}</a> &nbsp;&nbsp; URL: <a href="{{ $s->company_url }}">{{ $s->company_url }}</a></div>
                @if ($s->corporate_number)<div class="muted">Corporate Number: {{ $s->corporate_number }}</div>@endif
            </td>
        </tr>
    </table>

    {{-- Title + invoice no / date --}}
    <table class="titlebar">
        <tr>
            <td style="width: 50%; vertical-align: middle;"><span class="doc-title">PROFORMA INVOICE</span></td>
            <td>
                <table class="doc-meta">
                    <tr><td class="label" style="width: 45%;">Invoice No:</td><td style="font-weight: bold;">{{ $invoice->invoice_no }}</td></tr>
                    <tr><td class="label">Invoice Date:</td><td>{{ $date($invoice->issued_on) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Consignee --}}
    <table class="bar"><tr><td>CONSIGNEE DETAILS</td></tr></table>
    <table class="block">
        <tr><td>
            <strong>{{ $invoice->consignee_name }}</strong><br>
            {!! nl2br(e($invoice->consignee_address)) !!}<br>
            TEL: {{ $invoice->consignee_phone }} &nbsp;&nbsp;&nbsp; Email: {{ $invoice->consignee_email }}
        </td></tr>
    </table>

    {{-- Seller + shipment --}}
    <table class="bar"><tr><td>SELLER</td></tr></table>
    <table class="block">
        <tr><td><strong>{{ $s->company_name }}</strong><br>{{ $s->company_address }}</td></tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width: 50%;"><span class="label">Port of loading:</span> {{ $s->port_of_loading }}</td>
            <td><span class="label">Port of destination:</span> {{ $dest }}</td>
        </tr>
        <tr>
            <td><span class="label">Issue Date:</span> {{ $date($invoice->issued_on) }}</td>
            <td><span class="label">Expiry Date:</span> {{ $date($invoice->expires_on) }}</td>
        </tr>
    </table>

    {{-- Vehicle --}}
    <table class="bar"><tr><td>ORDER DETAILS</td></tr></table>
    <div class="unit-title">ONE UNIT - USED {{ strtoupper($unitTitle) }}@if (! empty($v['grade'])) <span class="muted" style="font-weight: normal;">({{ $v['grade'] }})</span>@endif</div>
    <table>
        <tr>
            <td class="photo">
                @if ($photo)<img src="{{ $photo }}">@endif
            </td>
            <td class="specs">
                <table>
                    <tr><td colspan="2" class="stock">STOCK NO: {{ $v['stock_no'] }}</td></tr>
                    @foreach (array_chunk($specs, 2, true) as $pair)
                        <tr>
                            @foreach ($pair as $k => $val)
                                <td class="k">{{ $k }}</td><td class="v">{{ $val }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($dimLine)
                        <tr><td class="k">Dimensions</td><td class="v" colspan="3">{{ $dimLine }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Price lines --}}
    <table class="lines">
        <thead>
            <tr><th>DESCRIPTION</th><th class="qty">Q'TY</th><th class="num">Unit Price</th><th class="num">US $</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>One Unit {{ $unitTitle }} — FOB Price</td>
                <td class="qty">1</td>
                <td class="num">{{ $money($invoice->price_fob) }}</td>
                <td class="num">{{ $money($invoice->price_fob) }}</td>
            </tr>
            @if ((float) $invoice->discount > 0)
                <tr class="neg"><td class="neg">Discount</td><td></td><td></td><td class="num neg">- {{ $money($invoice->discount) }}</td></tr>
            @endif
            <tr><td>Insurance Fee</td><td></td><td></td><td class="num">{{ $money($invoice->insurance) }}</td></tr>
            <tr><td>Freight Charge ({{ number_format((float) ($v['m3'] ?? 0), 2) }} M3)</td><td></td><td></td><td class="num">{{ $money($invoice->freight) }}</td></tr>
            <tr class="total">
                <td>TOTAL CIF {{ strtoupper($dest) }} &nbsp;US$</td>
                <td class="qty">1</td>
                <td></td>
                <td class="num">{{ $money($invoice->total_cif) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Payment --}}
    <table class="bar" style="margin-top: 5px;"><tr><td>PAYMENT INFORMATION</td></tr></table>
    <table class="pay">
        <tr><td style="width: 50%;">
            <table>
                <tr><td class="h">BENEFICIARY INFORMATION</td></tr>
                <tr><td><span class="label">Name:</span> {{ $s->company_name }}</td></tr>
                <tr><td><span class="label">Address:</span> {{ $s->company_address }}</td></tr>
                <tr><td><span class="label">Tel:</span> {{ $s->company_tel }}</td></tr>
                <tr><td><span class="label">Account No:</span> {{ $s->beneficiary_account_no }}</td></tr>
            </table>
        </td><td>
            <table>
                <tr><td class="h">BANK INFORMATION</td></tr>
                <tr><td><span class="label">Bank Name:</span> {{ $s->bank_name }}</td></tr>
                <tr><td><span class="label">Branch:</span> {{ $s->bank_branch }}</td></tr>
                <tr><td><span class="label">Bank Address:</span> {{ $s->bank_address }}</td></tr>
                <tr><td><span class="label">SWIFT Code:</span> {{ $s->bank_swift }}</td></tr>
                <tr><td><span class="label">Bank Charge:</span> {{ $s->bank_charge }}</td></tr>
            </table>
        </td></tr>
    </table>
    <div style="height: 5px;"></div>
</div>

<div class="terms"><strong>PAYMENT TERMS</strong> — {{ $s->payment_terms }}</div>

<table class="foot">
    <tr>
        <td style="width: 55%;">
            <span class="thanks">Thank you for your business!</span>
            {{-- Handshake mark (drawn, no stock image) --}}
            <img style="width: 34px; vertical-align: middle; margin-left: 6px;" src="data:image/svg+xml;base64,{{ base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 40"><path fill="#1f2356" d="M2 10l10-6 6 4-9 16-7-4zM62 10l-10-6-6 4 9 16 7-4z"/><path fill="#e30613" d="M18 9l10-3c3-1 6 0 8 1l11 2 7 13-5 4-14-9-6 3c-2 1-5 0-5-2l6-6-4 0-6 6z"/><path fill="#1f2356" d="M13 24l7-12 4 2-3 4c-1 3 2 6 6 5l5-2 13 8c2 1 2 4-1 5-1 1-3 0-3 0 1 2-1 4-3 3 0 2-2 3-4 2-1 2-3 2-4 1l-8-5z"/></svg>') }}">
        </td>
        <td class="sign">
            @if ($stamp)<img src="{{ $stamp }}" style="width: 88px; margin-bottom: -26px;"><br>@endif
            <span class="sign-name">{{ $s->signatory }}</span>
        </td>
    </tr>
</table>
</body>
</html>
