@php
    // Accent color chosen in the profile (snapshot on the invoice), with derived shades
    $c = \App\Support\InvoiceColors::palette($issuer['invoice_color'] ?? null);

    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => fmod((float) $value, 1) == 0 ? number_format((float) $value, 0, ',', '.') : number_format((float) $value, 2, ',', '.');
    $taxRate = rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, ',', ''), '0'), ',');

    $issuerCity = trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''));
    $issuerAddress = implode(' · ', array_filter([$issuer['street'] ?? null, $issuerCity]));
    $iban = ! empty($issuer['iban']) ? trim(chunk_split($issuer['iban'], 4, ' ')) : null;

    $paymentDays = (int) $invoice->invoice_date->diffInDays($invoice->due_date);
    $isCancellation = $invoice->cancels !== null;
    $isCredit = $invoice->amount_due < 0;
    $isCancelled = $invoice->cancellation !== null;
    $paid ??= null;   // ['date' => Carbon, 'cash' => bool, 'method' => ?string] when fully paid
    $noPayment = $isCredit || $isCancelled || $paid;
    $paidLabel = $paid ? (($paid['cash'] ? 'Bar erhalten am ' : 'Bezahlt am ').$paid['date']->format('d.m.Y')) : null;

    $showRecipientCountry = ! empty($recipient['country']) && ($recipient['country'] ?? null) !== ($issuer['country'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechnung {{ $invoice->number }}</title>
<style>
    @page {
        margin: 46px 48px 175px 48px;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9pt;
        color: #262626;
        line-height: 1.4;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    td {
        vertical-align: top;
    }

    .muted { color: #737373; }
    .right { text-align: right; }
    .nowrap { white-space: nowrap; }

    .label {
        font-size: 6.5pt;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #737373;
    }

    /* Brand bar along the top edge of every page */
    .brand-bar {
        position: fixed;
        top: -46px;
        left: -48px;
        right: -48px;
        height: 5px;
        background-color: {{ $c['base'] }};
    }

    /* Header: recipient left, logo + payment QR right */

    .sender-line {
        font-size: 6.5pt;
        color: #737373;
        padding-bottom: 3px;
        margin-bottom: 8px;
        border-bottom: 0.5px solid #d4d4d4;
    }

    .recipient {
        font-size: 10pt;
        line-height: 1.5;
    }

    .logo {
        max-height: 70px;
        max-width: 215px;
    }

    .issuer-name-fallback {
        font-size: 17pt;
        font-weight: bold;
        color: {{ $c['ink'] }};
    }

    .pay-box {
        margin-top: 14px;
        border: 1px solid {{ $c['tintStrong'] }};
        background-color: {{ $c['tint'] }};
    }

    .pay-box td {
        vertical-align: middle;
        padding: 7px;
    }

    .pay-box img {
        width: 66px;
        height: 66px;
    }

    .pay-title {
        font-weight: bold;
        font-size: 9pt;
        color: {{ $c['ink'] }};
    }

    .paid-box {
        margin-top: 14px;
        border: 1.5px solid {{ $c['base'] }};
        background-color: {{ $c['tint'] }};
        padding: 9px 12px;
        text-align: left;
    }

    .paid-title {
        font-size: 11pt;
        font-weight: bold;
        color: {{ $c['dark'] }};
        letter-spacing: 0.5px;
    }

    .paid-hint {
        font-size: 7.5pt;
        color: #525252;
    }

    .pay-amount {
        font-size: 12pt;
        font-weight: bold;
        color: {{ $c['dark'] }};
    }

    .pay-hint {
        font-size: 6.5pt;
        color: #737373;
    }

    /* Title + meta */

    .title {
        margin-top: 34px;
        font-size: 22pt;
        font-weight: bold;
        color: #0a0a0a;
        letter-spacing: -0.5px;
    }

    .title .number {
        color: {{ $c['base'] }};
    }

    .cancelled-badge {
        display: inline-block;
        margin-left: 10px;
        padding: 3px 8px;
        border: 1.5px solid #dc2626;
        color: #dc2626;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1.5px;
        vertical-align: middle;
    }

    .title-reference {
        margin-top: 4px;
        font-size: 9pt;
        color: #737373;
    }

    .meta {
        margin-top: 12px;
    }

    .meta td {
        width: 33.33%;
        padding: 9px 12px;
        background-color: #f5f5f5;
        border-left: 3px solid #ffffff;
    }

    .meta td.first {
        border-left: 0;
    }

    .meta .value {
        font-size: 10pt;
        font-weight: bold;
        color: #0a0a0a;
        padding-top: 2px;
    }

    .meta td.due {
        background-color: {{ $c['tint'] }};
    }

    .meta td.due .value {
        color: {{ $c['dark'] }};
    }

    /* Items */

    .items {
        margin-top: 26px;
    }

    .items th {
        font-size: 6.5pt;
        font-weight: normal;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #737373;
        text-align: left;
        padding: 0 6px 7px;
        border-bottom: 1.5px solid {{ $c['base'] }};
    }

    .items td {
        padding: 9px 6px;
        border-bottom: 0.5px solid #e5e5e5;
    }

    .items .pos {
        width: 22px;
        color: #a3a3a3;
    }

    .items .num {
        text-align: right;
        white-space: nowrap;
    }

    .items .unit {
        text-align: center;
        color: #525252;
    }

    .item-title {
        font-weight: bold;
        color: #0a0a0a;
    }

    .item-detail {
        color: #737373;
        font-size: 8pt;
        padding-top: 2px;
    }

    /* Lines starting with "- " in the description become bullet points */
    .item-bullets {
        width: auto;
        margin-top: 3px;
    }

    .item-bullets td {
        padding: 1px 0;
        border: 0;
        font-size: 8.5pt;
        color: #404040;
    }

    .item-bullets td.dot {
        width: 12px;
        color: {{ $c['base'] }};
        font-weight: bold;
    }

    /* Totals */

    .totals {
        width: 52%;
        margin-left: 48%;
        margin-top: 16px;
    }

    .totals td {
        padding: 5px 6px;
    }

    .totals .num {
        text-align: right;
        white-space: nowrap;
    }

    .totals .sum td {
        border-top: 0.5px solid #d4d4d4;
        padding-top: 8px;
        font-weight: bold;
        color: #0a0a0a;
    }

    .amount-due {
        margin-top: 10px;
        width: 52%;
        margin-left: 48%;
        background-color: {{ $c['base'] }};
        color: {{ $c['onBase'] }};
    }

    .amount-due td {
        padding: 11px 12px;
        vertical-align: middle;
    }

    .amount-due .label {
        color: {{ $c['onBaseMuted'] }};
    }

    .amount-due.cancelled {
        background-color: #a3a3a3;
        color: #ffffff;
    }

    .amount-due.cancelled .label {
        color: #f5f5f5;
    }

    .amount-due .value {
        font-size: 15pt;
        font-weight: bold;
        text-align: right;
        white-space: nowrap;
    }

    .notes {
        margin-top: 30px;
        padding: 10px 12px;
        border-left: 3px solid {{ $c['base'] }};
        background-color: #fafafa;
        color: #404040;
    }

    /* Footer: three columns with the sender details, on every page */

    .footer {
        position: fixed;
        bottom: -118px;
        left: 0;
        right: 0;
        height: 100px;
    }

    .footer-columns {
        border-top: 0.5px solid #d4d4d4;
    }

    .footer-columns td {
        width: 33.33%;
        padding: 12px 10px 0 0;
        font-size: 7.5pt;
        line-height: 1.55;
        color: #404040;
    }

    .footer-columns .label {
        color: {{ $c['dark'] }};
        font-weight: bold;
        padding-bottom: 3px;
    }

    .footer-columns strong {
        color: #0a0a0a;
    }
</style>
</head>
<body>

<div class="brand-bar"></div>

{{-- Footer first, so dompdf repeats it on every page. Page numbers are drawn by InvoiceController. --}}
<div class="footer">
    <table class="footer-columns">
        <tr>
            <td>
                <div class="label">Kontakt</div>
                @if (! empty($issuer['name']))<strong>{{ $issuer['name'] }}</strong><br>@endif
                @if ($issuerAddress){{ str_replace(' · ', ', ', $issuerAddress) }}<br>@endif
                @if (! empty($issuer['email'])){{ $issuer['email'] }}<br>@endif
                {{ implode(' · ', array_filter([$issuer['phone'] ?? null, $issuer['website'] ?? null])) }}
            </td>
            <td>
                <div class="label">Bankverbindung</div>
                @if (! empty($issuer['bank_name']) || ! empty($issuer['account_holder']))<strong>{{ implode(' · ', array_filter([$issuer['bank_name'] ?? null, $issuer['account_holder'] ?? null])) }}</strong><br>@endif
                @if ($iban)<span class="nowrap">IBAN: {{ $iban }}</span><br>@endif
                @if (! empty($issuer['bic']))BIC: {{ $issuer['bic'] }}@endif
            </td>
            <td>
                <div class="label">Zahlung &amp; Steuer</div>
                @if ($paid)
                    {{ $paidLabel }}<br>
                @elseif ($noPayment)
                    Keine Zahlung fällig<br>
                @else
                    Zahlbar bis <strong>{{ $invoice->due_date->format('d.m.Y') }}</strong><br>
                @endif
                Verwendungszweck: {{ $invoice->number }}<br>
                @if (! empty($issuer['uid_number']))UID: {{ $issuer['uid_number'] }}@endif
            </td>
        </tr>
    </table>
</div>

{{-- Header --}}
<table>
    <tr>
        <td style="padding-top: 70px;">
            @if (! empty($issuer['name']))
                <div class="sender-line">{{ $issuer['name'] }} · {{ $issuerAddress }}</div>
            @endif

            <div class="recipient">
                <strong>{{ $recipient['name'] ?? '' }}</strong><br>
                @if (! empty($recipient['street'])){{ $recipient['street'] }}<br>@endif
                @if (! empty($recipient['zip']) || ! empty($recipient['city'])){{ trim(($recipient['zip'] ?? '').' '.($recipient['city'] ?? '')) }}<br>@endif
                @if ($showRecipientCountry){{ $recipient['country'] }}<br>@endif
                @if (! empty($recipient['uid_number']))<span class="muted">UID: {{ $recipient['uid_number'] }}</span>@endif
            </div>
        </td>
        <td style="width: 215px;" class="right">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Logo">
            @elseif (! empty($issuer['name']))
                <span class="issuer-name-fallback">{{ $issuer['name'] }}</span>
            @endif

            @if ($qrCode)
                <table class="pay-box">
                    <tr>
                        <td style="width: 70px;"><img src="{{ $qrCode }}" alt="Zahlungs-QR-Code"></td>
                        <td style="text-align: left;">
                            <div class="pay-title">Jetzt bezahlen</div>
                            <div class="pay-amount">€ {{ $money($invoice->amount_due) }}</div>
                            <div class="pay-hint">Per Banking-App scannen</div>
                        </td>
                    </tr>
                </table>
            @elseif ($paid)
                <div class="paid-box">
                    <div class="paid-title">✓ {{ $paid['cash'] ? 'BAR BEZAHLT' : 'BEZAHLT' }}</div>
                    <div class="paid-hint">{{ $paidLabel }}@if (! $paid['cash'] && $paid['method']) · {{ $paid['method'] }}@endif</div>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Title + meta --}}
<div class="title">
    {{ $isCancellation ? 'Stornorechnung' : 'Rechnung' }} <span class="number">{{ $invoice->number }}</span>
    @if ($invoice->cancellation)
        <span class="cancelled-badge">STORNIERT</span>
    @endif
</div>
@if ($isCancellation)
    <div class="title-reference">Storno zu Rechnung {{ $invoice->cancels->number }} vom {{ $invoice->cancels->invoice_date->format('d.m.Y') }}</div>
@elseif ($invoice->cancellation)
    <div class="title-reference">Diese Rechnung wurde durch Stornorechnung {{ $invoice->cancellation->number }} aufgehoben.</div>
@endif

<table class="meta">
    <tr>
        <td class="first">
            <div class="label">Rechnungsdatum</div>
            <div class="value">{{ $invoice->invoice_date->format('d.m.Y') }}</div>
        </td>
        @if ($isCancellation)
            <td>
                <div class="label">Storno zu</div>
                <div class="value">{{ $invoice->cancels->number }}</div>
            </td>
            <td class="due">
                <div class="label">Rechnung vom</div>
                <div class="value">{{ $invoice->cancels->invoice_date->format('d.m.Y') }}</div>
            </td>
        @elseif ($paid)
            <td>
                <div class="label">Zahlungsart</div>
                <div class="value">{{ $paid['cash'] ? 'Bar' : ($paid['method'] ?? 'Gemischt') }}</div>
            </td>
            <td class="due">
                <div class="label">Bezahlt am</div>
                <div class="value">{{ $paid['date']->format('d.m.Y') }}</div>
            </td>
        @else
            <td>
                <div class="label">Zahlungsziel</div>
                <div class="value">{{ $paymentDays === 0 ? 'Sofort' : $paymentDays.' Tage' }}</div>
            </td>
            <td class="due">
                <div class="label">Fällig am</div>
                <div class="value">{{ $invoice->due_date->format('d.m.Y') }}</div>
            </td>
        @endif
    </tr>
</table>

{{-- Items --}}
<table class="items">
    <thead>
        <tr>
            <th class="pos">Pos</th>
            <th>Beschreibung</th>
            <th class="num" style="width: 50px;">Menge</th>
            <th class="unit" style="width: 60px;">Einheit</th>
            <th class="num" style="width: 70px;">Preis</th>
            <th class="num" style="width: 75px;">Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td class="pos">{{ $loop->iteration }}</td>
                <td>@include('pdf.partials.item-description', ['description' => $item->description])</td>
                <td class="num">{{ $quantity($item->quantity) }}</td>
                <td class="unit">{{ $item->unit }}</td>
                <td class="num">{{ $money($item->unit_price) }}</td>
                <td class="num">{{ $money($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals --}}
<table class="totals">
    <tr>
        <td class="muted">Zwischensumme netto</td>
        <td class="num">€ {{ $money($invoice->subtotal) }}</td>
    </tr>
    <tr>
        <td class="muted">USt. {{ $taxRate }} %</td>
        <td class="num">€ {{ $money($invoice->tax_amount) }}</td>
    </tr>
    <tr class="sum">
        <td>Gesamt</td>
        <td class="num">€ {{ $money($invoice->total) }}</td>
    </tr>
    @if ((float) $invoice->paid_amount > 0)
        <tr>
            <td class="muted">Bereits bezahlt</td>
            <td class="num">− € {{ $money($invoice->paid_amount) }}</td>
        </tr>
    @endif
</table>

<table class="amount-due{{ $isCancelled ? ' cancelled' : '' }}">
    <tr>
        <td><div class="label">{{ $isCredit ? 'Gutschrift' : ($isCancelled ? 'Storniert – nicht zu zahlen' : ($paid ? $paidLabel : 'Zu zahlender Betrag')) }}</div></td>
        <td class="value">€ {{ $money($invoice->amount_due) }}</td>
    </tr>
</table>

@if ($invoice->notes)
    <div class="notes">{!! nl2br(e($invoice->notes)) !!}</div>
@endif

</body>
</html>
