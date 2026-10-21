@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $isCancellation = $invoice->cancels_invoice_id !== null;
    $amount = $invoice->amount_due;
    $paidInCash = $invoice->paid_in_cash;
    $iban = ! empty($issuer['iban']) ? trim(chunk_split($issuer['iban'], 4, ' ')) : null;
    $replyTo = $issuer['email'] ?? null;

    // Own logo in the header, embedded so it shows without loading external images.
    // The view renders twice (HTML + text): reuse the logo if it is already embedded.
    $logo = null;
    if (isset($message) && $logoPath) {
        $embedded = collect($message->getSymfonyMessage()->getAttachments())
            ->first(fn ($part) => $part->getFilename() === basename($logoPath));
        $logo = $embedded ? 'cid:'.$embedded->getContentId() : $message->embed($logoPath);
    }
@endphp
<x-mail::layout>
<x-slot:header>
<tr>
<td class="header">
@if ($logo)
<img src="{{ $logo }}" alt="{{ $senderName }}" style="max-height: 64px; max-width: 220px;">
@else
<span style="font-size: 22px; font-weight: 700; color: #0a0a0a;">{{ $senderName }}</span>
@endif
</td>
</tr>
</x-slot:header>

# {{ $isCancellation ? 'Stornorechnung' : 'Rechnung' }} {{ $invoice->number }}

{{ $salutation }}

@if ($isCancellation)
anbei erhalten Sie die **Stornorechnung {{ $invoice->number }}** zur Rechnung {{ $invoice->cancels?->number }}. Die ursprüngliche Rechnung ist damit aufgehoben, es ist nichts zu bezahlen.
@else
anbei erhalten Sie die **Rechnung {{ $invoice->number }}** vom {{ $invoice->invoice_date->format('d.m.Y') }} über **{{ $money($invoice->total) }}**.
@endif

@if ($personalMessage)
{{ $personalMessage }}

@endif
@if (! $isCancellation && $amount > 0)
@if ($paidInCash)
Der Betrag wurde bereits bar bezahlt – vielen Dank!
@else
<x-mail::panel>
**Zahlbar bis {{ $invoice->due_date->format('d.m.Y') }}: {{ $money($amount) }}**<br>
@if ($iban)
{{ $issuer['account_holder'] ?? $issuer['name'] ?? '' }}<br>
IBAN: {{ $iban }}@if (! empty($issuer['bic']))<br>BIC: {{ $issuer['bic'] }}@endif<br>
@endif
Verwendungszweck: {{ $invoice->number }}
</x-mail::panel>

Im PDF finden Sie auch einen QR-Code, den Sie mit Ihrer Banking-App scannen können.
@endif
@endif

Freundliche Grüße<br>
{{ $issuer['name'] ?? '' }}@if ($senderName && $senderName !== ($issuer['name'] ?? null))<br>
{{ $senderName }}@endif

<x-slot:subcopy>
<x-mail::subcopy>
@if ($replyTo)
**Diese E-Mail wurde automatisch erstellt.** Falls etwas nicht passt oder Sie Fragen zur Rechnung haben, antworten Sie einfach auf diese E-Mail oder schreiben Sie an [{{ $replyTo }}](mailto:{{ $replyTo }}).
@else
**Diese E-Mail wurde automatisch erstellt.** Falls etwas nicht passt oder Sie Fragen zur Rechnung haben, antworten Sie einfach auf diese E-Mail.
@endif
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
**{{ $senderName }}**@if ($senderName !== ($issuer['name'] ?? null)) · {{ $issuer['name'] ?? '' }}@endif<br>
{{ implode(', ', array_filter([$issuer['street'] ?? null, trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''))])) }}<br>
{{ implode(' · ', array_filter([$replyTo, $issuer['phone'] ?? null, $issuer['website'] ?? null])) }}
@if (! empty($issuer['uid_number']))<br>UID: {{ $issuer['uid_number'] }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
