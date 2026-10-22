@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
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

# Angebot {{ $offer->number }}

{{ $salutation }}

vielen Dank für Ihr Interesse! Anbei erhalten Sie mein **Angebot {{ $offer->number }}** für die {{ $offer->type_label }} „{{ $offer->title }}“.

@if ($personalMessage)
{{ $personalMessage }}

@endif
<x-mail::panel>
**Gesamtsumme: {{ $money($offer->total) }}** brutto<br>
inkl. laufender Betreuung für die ersten 12 Monate<br>
Gültig bis {{ $offer->valid_until->format('d.m.Y') }}
</x-mail::panel>

@if ($documents)
Im Anhang finden Sie außerdem: {{ implode(' und ', $documents) }}.

@endif
Wenn das Angebot für Sie passt, senden Sie es mir einfach unterschrieben zurück – oder antworten Sie auf diese E-Mail. Ich freue mich auf die Zusammenarbeit!

Beste Grüße<br>
{{ $signatureName }}@if ($senderName && $senderName !== $signatureName)<br>
{{ $senderName }}@endif

<x-slot:subcopy>
<x-mail::subcopy>
@if ($replyTo)
**Diese E-Mail wurde automatisch erstellt.** Falls etwas nicht passt oder Sie Fragen zum Angebot haben, antworten Sie einfach auf diese E-Mail oder schreiben Sie an [{{ $replyTo }}](mailto:{{ $replyTo }}).
@else
**Diese E-Mail wurde automatisch erstellt.** Falls etwas nicht passt oder Sie Fragen zum Angebot haben, antworten Sie einfach auf diese E-Mail.
@endif
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
**{{ $senderName }}**@if ($senderName !== $signatureName) · {{ $signatureName }}@endif<br>
{{ implode(', ', array_filter([$issuer['street'] ?? null, trim(($issuer['zip'] ?? '').' '.($issuer['city'] ?? ''))])) }}<br>
{{ implode(' · ', array_filter([$replyTo, $issuer['phone'] ?? null, $issuer['website'] ?? null])) }}
@if (! empty($issuer['uid_number']))<br>UID: {{ $issuer['uid_number'] }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
