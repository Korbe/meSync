@php
    // Same accent color and layout as the invoices
    $c = \App\Support\InvoiceColors::palette($issuer['invoice_color'] ?? null);

    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => fmod((float) $value, 1) == 0 ? number_format((float) $value, 0, ',', '.') : number_format((float) $value, 2, ',', '.');
    $taxRate = rtrim(rtrim(number_format((float) $offer->tax_rate, 2, ',', ''), '0'), ',');

    $issuerCity = trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''));
    $issuerAddress = implode(' · ', array_filter([$issuer['street'] ?? null, $issuerCity]));
    $iban = ! empty($issuer['iban']) ? trim(chunk_split($issuer['iban'], 4, ' ')) : null;

    $recipientAddress = implode(', ', array_filter([$recipient['street'] ?? null, trim(($recipient['zip'] ?? '').' '.($recipient['city'] ?? ''))]));
    $showRecipientCountry = ! empty($recipient['country']) && ($recipient['country'] ?? null) !== ($issuer['country'] ?? null);
    $isWebapp = $offer->type->value === 'webapp';
    $validDays = (int) $offer->offer_date->diffInDays($offer->valid_until);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Angebot {{ $offer->number }}</title>
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
    .page-break { page-break-before: always; }

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
        margin-top: 22px;
        font-size: 22pt;
        font-weight: bold;
        color: #0a0a0a;
        letter-spacing: -0.5px;
    }

    .title .number {
        color: {{ $c['base'] }};
    }

    .subtitle {
        margin-top: 2px;
        font-size: 10.5pt;
        color: #525252;
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

    .meta td.highlight {
        background-color: {{ $c['tint'] }};
    }

    .meta td.highlight .value {
        color: {{ $c['dark'] }};
    }

    .contact {
        margin-top: 6px;
        font-size: 8pt;
        color: #525252;
    }

    .intro {
        margin-top: 14px;
        color: #404040;
    }

    h2 {
        margin: 18px 0 6px;
        padding-bottom: 5px;
        font-size: 10.5pt;
        color: #0a0a0a;
        border-bottom: 1.5px solid {{ $c['base'] }};
    }

    .choice {
        margin-top: 6px;
    }

    .choice td {
        padding: 3px 0;
    }

    .choice td.box {
        width: 16px;
        font-size: 11pt;
        line-height: 1;
        color: {{ $c['dark'] }};
    }

    .choice .name {
        font-weight: bold;
        color: #0a0a0a;
    }


    /* Items */

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
        padding: 7px 6px;
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

    .items tr.maintenance td {
        background-color: {{ $c['tint'] }};
    }

    .list-price {
        color: #a3a3a3;
        text-decoration: line-through;
        font-size: 8pt;
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

    .mandatory {
        display: inline-block;
        margin-left: 4px;
        padding: 1px 5px;
        border: 0.75px solid {{ $c['base'] }};
        color: {{ $c['dark'] }};
        font-size: 6pt;
        font-weight: bold;
        letter-spacing: 0.8px;
    }

    /* Totals */

    .totals {
        width: 52%;
        margin-left: 48%;
        margin-top: 14px;
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

    .grand-total {
        margin-top: 8px;
        width: 52%;
        margin-left: 48%;
        background-color: {{ $c['base'] }};
        color: {{ $c['onBase'] }};
    }

    .grand-total td {
        padding: 11px 12px;
        vertical-align: middle;
    }

    .grand-total .label {
        color: {{ $c['onBaseMuted'] }};
    }

    .grand-total .value {
        font-size: 15pt;
        font-weight: bold;
        text-align: right;
        white-space: nowrap;
    }

    .followup {
        margin-top: 6px;
        width: 52%;
        margin-left: 48%;
        font-size: 7.5pt;
        color: #737373;
        text-align: right;
    }

    /* Maintenance */

    .maintenance-box {
        margin-top: 4px;
        padding: 10px 12px;
        border-left: 3px solid {{ $c['base'] }};
        background-color: {{ $c['tint'] }};
    }

    .services td {
        padding: 2px 0;
        font-size: 8.5pt;
    }

    .services td.dot {
        width: 12px;
        color: {{ $c['base'] }};
        font-weight: bold;
    }

    .features td {
        width: 33.33%;
        padding: 3px 4px 3px 0;
    }

    .notes {
        margin-top: 14px;
        padding: 10px 12px;
        border-left: 3px solid {{ $c['base'] }};
        background-color: #fafafa;
        color: #404040;
    }

    .terms {
        margin-top: 10px;
        color: #404040;
    }

    .hint {
        margin-top: 6px;
        font-size: 7.5pt;
        font-style: italic;
        color: #737373;
    }

    /* Signatures */

    .signatures {
        margin-top: 46px;
        page-break-inside: avoid;
    }

    .signatures td {
        width: 33.33%;
        padding: 6px 12px 0 0;
        border-top: 0.75px solid #404040;
        font-size: 7.5pt;
        color: #525252;
    }

    .signatures td.gap {
        width: 4%;
        border-top: 0;
    }

    /* Own signature sits on the line, the cells above stay empty for signing by hand */
    .signatures tr.sign td {
        height: 52px;
        padding: 0;
        border-top: 0;
        vertical-align: bottom;
    }

    .signatures img {
        max-height: 50px;
        max-width: 170px;
    }

    /* Footer */

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
                <div class="label">Angebot</div>
                {{ $offer->number }} vom {{ $offer->offer_date->format('d.m.Y') }}<br>
                Gültig bis <strong>{{ $offer->valid_until->format('d.m.Y') }}</strong><br>
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
            @if (! empty($recipient['phone']) || ! empty($recipient['email']))
                <div class="contact">{{ implode(' · ', array_filter([$recipient['phone'] ?? null, $recipient['email'] ?? null])) }}</div>
            @endif
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

{{-- Title + meta --}}
<div class="title">Angebot <span class="number">{{ $offer->number }}</span></div>
<div class="subtitle">Erstellung einer {{ $offer->type_label }} – {{ $offer->title }}@if ($offer->url) · {{ $offer->url }}@endif</div>

<table class="meta">
    <tr>
        <td class="first">
            <div class="label">Angebotsdatum</div>
            <div class="value">{{ $offer->offer_date->format('d.m.Y') }}</div>
        </td>
        <td>
            <div class="label">Gültig bis</div>
            <div class="value">{{ $offer->valid_until->format('d.m.Y') }}</div>
        </td>
        <td class="highlight">
            <div class="label">Gesamt brutto</div>
            <div class="value">€ {{ $money($offer->total) }}</div>
        </td>
    </tr>
</table>

@if ($offer->intro)
    <div class="intro">{!! nl2br(e($offer->intro)) !!}</div>
@endif

{{-- Items: project + mandatory maintenance --}}
<h2>Leistungen</h2>
<table class="items">
    <thead>
        <tr>
            <th class="pos">Pos</th>
            <th>Beschreibung</th>
            <th class="num" style="width: 50px;">Menge</th>
            <th class="unit" style="width: 60px;">Einheit</th>
            <th class="num" style="width: 80px;">Preis</th>
            <th class="num" style="width: 75px;">Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($offer->items as $item)
            <tr>
                <td class="pos">{{ $loop->iteration }}</td>
                <td>@include('pdf.partials.item-description', ['description' => $item->description])</td>
                <td class="num">{{ $quantity($item->quantity) }}</td>
                <td class="unit">{{ $item->unit }}</td>
                <td class="num">
                    @if ($item->hasDiscount())<span class="list-price">{{ $money($item->list_price) }}</span><br>@endif
                    {{ $money($item->unit_price) }}
                </td>
                <td class="num">{{ $money($item->line_total) }}</td>
            </tr>
        @endforeach
        <tr class="maintenance">
            <td class="pos">{{ $offer->items->count() + 1 }}</td>
            <td>
                <div class="item-title">Laufende Betreuung (12 Monate)<span class="mandatory">VERPFLICHTEND</span></div>
                <div class="item-detail">{{ implode(' · ', array_filter([$offer->type_label.' Wartung & Betreuung', $offer->maintenance_package ? 'Paket '.$offer->maintenance_package : null])) }}</div>
                <div class="item-detail">{{ $hints['maintenance_start'] }}</div>
            </td>
            <td class="num">12</td>
            <td class="unit">Monat</td>
            <td class="num">{{ $money($offer->maintenance_monthly_price) }}</td>
            <td class="num">{{ $money($offer->maintenance_annual_price) }}</td>
        </tr>
    </tbody>
</table>

{{-- Totals stay together on one page --}}
<div style="page-break-inside: avoid;">
<table class="totals">
    <tr>
        <td class="muted">Summe netto</td>
        <td class="num">€ {{ $money($offer->subtotal) }}</td>
    </tr>
    <tr>
        <td class="muted">USt. {{ $taxRate }} %</td>
        <td class="num">€ {{ $money($offer->tax_amount) }}</td>
    </tr>
</table>

<table class="grand-total">
    <tr>
        <td><div class="label">Gesamtsumme brutto</div></td>
        <td class="value">€ {{ $money($offer->total) }}</td>
    </tr>
</table>
<div class="followup">
    Ab dem zweiten Jahr: Betreuung € {{ $money($offer->maintenance_annual_price) }} netto pro Jahr, jährlich im Voraus.
</div>
</div>

{{-- Maintenance: always part of the offer (heading stays with its box) --}}
<div style="page-break-inside: avoid;">
<h2>Laufende Betreuung &amp; Wartung</h2>
<div class="maintenance-box">
    <strong>Die laufende Betreuung ist fester Bestandteil dieses Angebots.</strong>
    Sie beginnt mit der Inbetriebnahme, wird jährlich im Voraus verrechnet und umfasst
    @if ($offer->maintenance_package)
        (Betreuungspaket <strong>{{ $offer->maintenance_package }}</strong>):
    @else
        folgende Leistungen:
    @endif
    <table class="services" style="margin-top: 5px;">
        @foreach ($services as $service)
            <tr><td class="dot">•</td><td>{{ $service }}</td></tr>
        @endforeach
    </table>
</div>

</div>

{{-- Payment + terms --}}
<h2>Zahlungsbedingungen</h2>
<table class="choice">
    <tr>
        <td class="box">☑</td>
        <td>{{ $paymentTerms[$offer->payment_terms] ?? $offer->payment_terms }}</td>
    </tr>
</table>

@if ($offer->notes)
    <div class="notes">{!! nl2br(e($offer->notes)) !!}</div>
@endif

<div class="terms">
    Dieses Angebot ist ab dem Ausstellungsdatum {{ $validDays }} Tage gültig (bis {{ $offer->valid_until->format('d.m.Y') }}). {{ ($termsAttached ?? false) ? rtrim($hints['terms'], '.').' (liegen bei).' : $hints['terms'] }}
</div>

<table class="signatures"{!! $signature ? ' style="margin-top: 10px;"' : '' !!}>
    @if ($signature)
        <tr class="sign">
            <td></td>
            <td class="gap"></td>
            <td></td>
            <td class="gap"></td>
            <td><img src="{{ $signature }}" alt="Unterschrift"></td>
        </tr>
    @endif
    <tr>
        <td>Ort, Datum</td>
        <td class="gap"></td>
        <td>Unterschrift Auftraggeber</td>
        <td class="gap"></td>
        <td>Unterschrift {{ $issuer['name'] ?? 'Auftragnehmer' }}</td>
    </tr>
</table>

{{-- Scope details: only what was chosen --}}
@php
    $chosenPackage = $packages[$offer->package] ?? null;
    $chosenFeatures = array_intersect_key($features, array_flip($offer->features ?? []));
    $chosenIntegrations = array_intersect_key($integrations, array_flip($offer->integrations ?? []));
    $checked = fn (array $labels) => collect($labels)->values()->chunk(2);
@endphp
<div class="page-break"></div>

<h2 style="margin-top: 0;">Art des Projekts</h2>
<table class="choice">
    <tr>
        <td class="box">☑</td>
        <td>{{ $types[$offer->type->value] }}</td>
    </tr>
</table>

@if ($chosenPackage)
    <h2>{{ $isWebapp ? $chosenPackage['name'] : 'Paket: '.$chosenPackage['name'] }}</h2>
    @if (! empty($chosenPackage['description']))
        <div>{{ $chosenPackage['description'] }}</div>
    @endif
    @if (! $isWebapp && ! empty($chosenPackage['features']))
        <table class="services">
            @foreach ($chosenPackage['features'] as $feature)
                <tr><td class="dot">•</td><td>{{ $feature }}</td></tr>
            @endforeach
        </table>
    @endif
    @if (! $isWebapp)
        <div class="hint">{{ $hints['individual'] }}</div>
    @endif
@endif

@if ($chosenFeatures)
    <h2>Zusatzoptionen</h2>
    <table class="features choice">
        @foreach ($checked($chosenFeatures) as $row)
            <tr>
                @foreach ($row as $label)
                    <td style="width: 50%;"><span style="color: {{ $c['dark'] }};">☑</span> {{ $label }}</td>
                @endforeach
                @if ($row->count() === 1)<td></td>@endif
            </tr>
        @endforeach
    </table>
@endif

@if ($chosenIntegrations || $offer->other_function || $offer->content_delivery || $offer->reference_customer)
    <h2>Projektdetails</h2>

    @if ($chosenIntegrations)
        <div style="font-weight: bold;">Anbindung an andere Systeme</div>
        <table class="features choice">
            @foreach ($checked($chosenIntegrations) as $row)
                <tr>
                    @foreach ($row as $label)
                        <td style="width: 50%;"><span style="color: {{ $c['dark'] }};">☑</span> {{ $label }}</td>
                    @endforeach
                    @if ($row->count() === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    @if ($offer->other_function)
        <div style="margin-top: 10px;"><strong>Sonstige Funktion:</strong> {{ $offer->other_function }}</div>
    @endif

    @if ($offer->content_delivery)
        <div style="margin-top: 10px;"><strong>Content-Lieferung (Texte/Bilder):</strong> {{ $contentDelivery[$offer->content_delivery] ?? $offer->content_delivery }}</div>
    @endif

    @if ($offer->reference_customer)
        <table class="choice" style="margin-top: 10px;">
            <tr>
                <td class="box">☑</td>
                <td>Der Auftraggeber darf als Referenzkunde angegeben werden.</td>
            </tr>
        </table>
    @endif
@endif

<div class="hint" style="margin-top: 14px;">{{ $hints['shop'] }}</div>

</body>
</html>
