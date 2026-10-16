@php
    $c = \App\Support\InvoiceColors::palette($issuer['invoice_color'] ?? null);

    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $date = fn ($value) => $value?->format('d.m.Y');
    $taxRate = rtrim(rtrim(number_format($prices['tax_rate'], 2, ',', ''), '0'), ',');

    $issuerCity = trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''));
    $issuerAddress = implode(' · ', array_filter([$issuer['street'] ?? null, $issuerCity]));
    $iban = ! empty($issuer['iban']) ? trim(chunk_split($issuer['iban'], 4, ' ')) : null;
    $recipientCity = trim(($recipient['zip'] ?? '').' '.($recipient['city'] ?? ''));
    $showRecipientCountry = ! empty($recipient['country']) && ($recipient['country'] ?? null) !== ($issuer['country'] ?? null);

    $ended = $contract->end_date && $contract->end_date->isPast();
    $billable = $contract->isBillable();
    $nextPeriodEnd = $contract->next_invoice_date->copy()->addYear()->subDay();
    $url = $contract->url ? preg_replace('#^https?://#', '', rtrim($contract->url, '/')) : null;

    if ($contract->monitor_enabled && $contract->url) {
        $services[] = 'Laufende Überwachung der Erreichbarkeit und des SSL-Zertifikats';
    }
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Wartungsvertrag {{ $contract->name }}</title>
<style>
    @page { margin: 46px 48px 175px 48px; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9pt;
        color: #262626;
        line-height: 1.4;
    }

    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }

    .muted { color: #737373; }
    .right { text-align: right; }
    .nowrap { white-space: nowrap; }

    .label {
        font-size: 6.5pt;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #737373;
    }

    .brand-bar {
        position: fixed;
        top: -46px; left: -48px; right: -48px;
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

    .recipient { font-size: 10pt; line-height: 1.5; }
    .logo { max-height: 70px; max-width: 215px; }
    .issuer-name-fallback { font-size: 17pt; font-weight: bold; color: {{ $c['ink'] }}; }

    .status {
        margin-top: 14px;
        display: inline-block;
        padding: 5px 10px;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1.2px;
        border: 1.5px solid {{ $c['base'] }};
        color: {{ $c['dark'] }};
        background-color: {{ $c['tint'] }};
    }

    .status.ended { border-color: #a3a3a3; color: #737373; background-color: #f5f5f5; }

    .title {
        margin-top: 26px;
        font-size: 22pt;
        font-weight: bold;
        color: #0a0a0a;
        letter-spacing: -0.5px;
    }

    .title .name { color: {{ $c['base'] }}; }
    .subtitle { margin-top: 3px; font-size: 9pt; color: #737373; }

    .meta { margin-top: 12px; }

    .meta td {
        width: 33.33%;
        padding: 9px 12px;
        background-color: #f5f5f5;
        border-left: 3px solid #ffffff;
    }

    .meta td.first { border-left: 0; }
    .meta .value { font-size: 10pt; font-weight: bold; color: #0a0a0a; padding-top: 2px; }
    .meta td.highlight { background-color: {{ $c['tint'] }}; }
    .meta td.highlight .value { color: {{ $c['dark'] }}; }

    h2 {
        margin: 20px 0 0;
        padding-bottom: 6px;
        font-size: 6.5pt;
        font-weight: normal;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #737373;
        border-bottom: 1.5px solid {{ $c['base'] }};
    }

    .parties td { width: 50%; padding: 10px 12px 0 0; line-height: 1.5; }
    .parties .role { font-weight: bold; color: {{ $c['dark'] }}; padding-bottom: 2px; }


    .services { margin-top: 8px; }
    .services td { padding: 3px 0; border: 0; color: #404040; }
    .services td.service { width: 47%; padding-right: 12px; }
    .services td.dot { width: 12px; color: {{ $c['base'] }}; font-weight: bold; }

    .costs { margin-top: 12px; }
    .costs .billing-cell { width: 50%; padding-right: 18px; }
    .costs .prices-cell { width: 50%; }

    .prices { margin-top: -5px; }
    .prices td { padding: 5px 6px; }
    .prices .num { text-align: right; white-space: nowrap; }
    .prices .sum td { border-top: 0.5px solid #d4d4d4; padding-top: 8px; font-weight: bold; color: #0a0a0a; }

    .total {
        margin-top: 8px;
        background-color: {{ $c['base'] }};
        color: {{ $c['onBase'] }};
    }

    .total td { padding: 11px 12px; vertical-align: middle; }
    .total .label { color: {{ $c['onBaseMuted'] }}; }
    .total .value { font-size: 15pt; font-weight: bold; text-align: right; white-space: nowrap; }

    .billing {
        padding: 10px 12px;
        border-left: 3px solid {{ $c['base'] }};
        background-color: #fafafa;
        color: #404040;
    }

    .footer { position: fixed; bottom: -118px; left: 0; right: 0; height: 100px; }
    .footer-columns { border-top: 0.5px solid #d4d4d4; }

    .footer-columns td {
        width: 33.33%;
        padding: 12px 10px 0 0;
        font-size: 7.5pt;
        line-height: 1.55;
        color: #404040;
    }

    .footer-columns .label { color: {{ $c['dark'] }}; font-weight: bold; padding-bottom: 3px; }
    .footer-columns strong { color: #0a0a0a; }
</style>
</head>
<body>

<div class="brand-bar"></div>

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
                <div class="label">Dokument</div>
                Vertragsinformation, Stand {{ today()->format('d.m.Y') }}<br>
                Kein Zahlungsbeleg, keine Rechnung<br>
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
                @if ($recipientCity){{ $recipientCity }}<br>@endif
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
            <br>
            <span class="status{{ $ended ? ' ended' : '' }}">{{ $ended ? 'BEENDET' : 'AKTIVER VERTRAG' }}</span>
        </td>
    </tr>
</table>

{{-- Title + meta --}}
<div class="title">Wartungsvertrag <span class="name">{{ $contract->type_label }}</span></div>
<div class="subtitle">{{ $contract->name }}@if ($url && $url !== $contract->name) · {{ $url }}@endif</div>

<table class="meta">
    <tr>
        <td class="first">
            <div class="label">Vertragsbeginn</div>
            <div class="value">{{ $date($contract->start_date) }}</div>
        </td>
        <td>
            <div class="label">Laufzeit</div>
            <div class="value">{{ $contract->end_date ? 'bis '.$date($contract->end_date) : 'Unbefristet' }}</div>
        </td>
        <td class="highlight">
            <div class="label">Jahresbetrag brutto</div>
            <div class="value">€ {{ $money($prices['annual_gross']) }}</div>
        </td>
    </tr>
</table>

{{-- Parties --}}
<h2>Vertragspartner</h2>
<table class="parties">
    <tr>
        <td>
            <div class="role">Auftraggeber</div>
            {{ $recipient['name'] ?? '' }}<br>
            @if (! empty($recipient['street'])){{ $recipient['street'] }}, @endif{{ $recipientCity }}
            @if (! empty($contract->customer->email))<br><span class="muted">{{ $contract->customer->email }}</span>@endif
        </td>
        <td>
            <div class="role">Auftragnehmer</div>
            {{ $issuer['name'] ?? '' }}<br>
            {{ str_replace(' · ', ', ', $issuerAddress) }}
            @if (! empty($issuer['email']))<br><span class="muted">{{ $issuer['email'] }}</span>@endif
        </td>
    </tr>
</table>

{{-- Subject: name, type and address are in the title, so only the services here --}}
<h2>Leistungsumfang – {{ $contract->type_label }} Wartung &amp; Betreuung</h2>
<table class="services">
    @foreach (array_chunk($services, 2) as $row)
        <tr>
            @foreach ($row as $service)
                <td class="dot">•</td><td class="service">{{ $service }}</td>
            @endforeach
            @if (count($row) === 1)<td></td><td></td>@endif
        </tr>
    @endforeach
</table>

{{-- Costs + billing side by side --}}
<h2>Kosten &amp; Abrechnung</h2>
<table class="costs">
    <tr>
        <td class="billing-cell">
            <div class="billing">
                <strong>Jährlich im Voraus</strong>, jeweils für 12 Monate.<br>
                @if ($period)
                    Zuletzt verrechnet: {{ $date($period->period_start) }} – {{ $date($period->period_end) }}@if ($period->invoice_number) (Rechnung {{ $period->invoice_number }})@endif.<br>
                @endif
                @if ($ended)
                    Der Vertrag endete am {{ $date($contract->end_date) }}.
                @elseif ($billable)
                    Nächste Verrechnung am <strong>{{ $date($contract->next_invoice_date) }}</strong> für {{ $date($contract->next_invoice_date) }} – {{ $date($contract->end_date && $contract->end_date->lt($nextPeriodEnd) ? $contract->end_date : $nextPeriodEnd) }}.
                @else
                    Der Vertrag endet am {{ $date($contract->end_date) }}, es folgt keine weitere Verrechnung.
                @endif
            </div>
        </td>
        <td class="prices-cell">
            <table class="prices">
                <tr>
                    <td class="muted">Monatlich netto</td>
                    <td class="num">€ {{ $money($prices['monthly']) }}</td>
                </tr>
                <tr class="sum">
                    <td>Jahresbetrag netto</td>
                    <td class="num">€ {{ $money($prices['annual']) }}</td>
                </tr>
                <tr>
                    <td class="muted">USt. {{ $taxRate }} %</td>
                    <td class="num">€ {{ $money($prices['tax']) }}</td>
                </tr>
            </table>
            <table class="total">
                <tr>
                    <td><div class="label">Jahresbetrag brutto</div></td>
                    <td class="value">€ {{ $money($prices['annual_gross']) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
