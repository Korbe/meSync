@php
    // Same accent color and sender details as the invoice
    $c = \App\Support\InvoiceColors::palette($issuer['invoice_color'] ?? null);

    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $issuerCity = trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''));
    $issuerAddress = implode(' · ', array_filter([$issuer['street'] ?? null, $issuerCity]));

    $cash = $payment->method === \App\Enums\PaymentMethod::Cash;
    $how = match ($payment->method) {
        \App\Enums\PaymentMethod::Cash => 'in bar',
        \App\Enums\PaymentMethod::Card => 'per Karte',
        default => 'per Überweisung',
    };
    $partial = $openAfter > 0;
    $showRecipientCountry = ! empty($recipient['country']) && ($recipient['country'] ?? null) !== ($issuer['country'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Zahlungsbestätigung {{ $invoice->number }}</title>
<style>
    @page {
        margin: 46px 48px 140px 48px;
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

    .label {
        font-size: 6.5pt;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #737373;
    }

    .brand-bar {
        position: fixed;
        top: -46px;
        left: -48px;
        right: -48px;
        height: 5px;
        background-color: {{ $c['base'] }};
    }

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

    .title {
        margin-top: 26px;
        font-size: 22pt;
        font-weight: bold;
        color: #0a0a0a;
        letter-spacing: -0.5px;
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

    .received {
        margin-top: 20px;
        border: 1.5px solid {{ $c['base'] }};
        background-color: {{ $c['tint'] }};
        padding: 14px 16px;
    }

    .received-title {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1.2px;
        color: {{ $c['dark'] }};
    }

    .received-amount {
        margin-top: 2px;
        font-size: 20pt;
        font-weight: bold;
        color: {{ $c['dark'] }};
    }

    .statement {
        margin-top: 18px;
        font-size: 10pt;
        line-height: 1.6;
    }

    .balance {
        margin-top: 14px;
        width: 60%;
    }

    .balance td {
        padding: 6px 0;
        border-bottom: 0.5px solid #e5e5e5;
    }

    .balance tr.total td {
        border-bottom: 0;
        border-top: 1.5px solid {{ $c['base'] }};
        font-weight: bold;
        color: #0a0a0a;
        padding-top: 8px;
    }

    .note {
        margin-top: 16px;
        padding: 10px 12px;
        border-left: 3px solid {{ $c['base'] }};
        background-color: #fafafa;
        color: #404040;
    }

    .signature {
        margin-top: 30px;
        width: 50%;
        page-break-inside: avoid;
    }

    .signature img {
        max-height: 60px;
        max-width: 200px;
    }

    .signature .line {
        border-top: 0.5px solid #a3a3a3;
        padding-top: 4px;
        font-size: 8pt;
        color: #525252;
    }

    .footer {
        position: fixed;
        bottom: -90px;
        left: 0;
        right: 0;
        height: 70px;
        border-top: 0.5px solid #d4d4d4;
        padding-top: 10px;
        font-size: 7.5pt;
        line-height: 1.55;
        color: #404040;
    }
</style>
</head>
<body>

<div class="brand-bar"></div>

<div class="footer">
    @if (! empty($issuer['name']))<strong>{{ $issuer['name'] }}</strong> · @endif{{ str_replace(' · ', ', ', $issuerAddress) }}<br>
    {{ implode(' · ', array_filter([$issuer['email'] ?? null, $issuer['phone'] ?? null, $issuer['website'] ?? null])) }}
    @if (! empty($issuer['uid_number']))<br>UID: {{ $issuer['uid_number'] }}@endif
</div>

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
            </div>
        </td>
        <td style="width: 215px;" class="right">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="Logo">
            @elseif (! empty($issuer['name']))
                <span class="issuer-name-fallback">{{ $issuer['name'] }}</span>
            @endif
        </td>
    </tr>
</table>

<div class="title">Zahlungsbestätigung</div>
<div class="title-reference">zur Rechnung {{ $invoice->number }} vom {{ $invoice->invoice_date->format('d.m.Y') }}</div>

<table class="meta">
    <tr>
        <td class="first">
            <div class="label">Erhalten am</div>
            <div class="value">{{ $payment->paid_at->format('d.m.Y') }}</div>
        </td>
        <td>
            <div class="label">Zahlungsart</div>
            <div class="value">{{ $payment->method->label() }}</div>
        </td>
        <td>
            <div class="label">Rechnung</div>
            <div class="value">{{ $invoice->number }}</div>
        </td>
    </tr>
</table>

<div class="received">
    <div class="received-title">{{ $cash ? '✓ BAR ERHALTEN' : '✓ ERHALTEN' }}{{ $partial ? ' · TEILZAHLUNG' : '' }}</div>
    <div class="received-amount">€ {{ $money($payment->amount) }}</div>
</div>

<div class="statement">
    Hiermit bestätige ich, am <strong>{{ $payment->paid_at->format('d.m.Y') }}</strong> den Betrag von
    <strong>€ {{ $money($payment->amount) }}</strong> {{ $how }} für die Rechnung Nr. {{ $invoice->number }} erhalten zu haben.
    @if ($partial)
        Es handelt sich um eine Teilzahlung, der restliche Betrag ist laut Rechnung bis {{ $invoice->due_date->format('d.m.Y') }} zu bezahlen.
    @else
        Die Rechnung ist damit vollständig bezahlt.
    @endif
</div>

<table class="balance">
    <tr>
        <td>Rechnungsbetrag</td>
        <td class="right">€ {{ $money($invoice->amount_due) }}</td>
    </tr>
    <tr>
        <td>Bezahlt inkl. dieser Zahlung</td>
        <td class="right">€ {{ $money($paidSoFar) }}</td>
    </tr>
    <tr class="total">
        <td>Offen</td>
        <td class="right">€ {{ $money($openAfter) }}</td>
    </tr>
</table>

@if ($payment->note)
    <div class="note">{{ $payment->note }}</div>
@endif

<div class="signature">
    <div style="height: 64px;">
        @if ($signature)<img src="{{ $signature }}" alt="Unterschrift" style="margin-top: 4px;">@endif
    </div>
    <div class="line">{{ trim(($issuer['city'] ?? '').', '.$payment->paid_at->format('d.m.Y'), ', ') }} · {{ $signatureName }}</div>
</div>

</body>
</html>
