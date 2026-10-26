@php
    $replyTo = $issuer['email'] ?? null;

    // Own logo in the header: embedded, with a fixed size (Outlook ignores max-width)
    $logo = \App\Support\MailLogo::embed($message ?? null, $logoPath);
@endphp
<x-mail::layout>
<x-slot:header>
<tr>
<td class="header">
@if ($logo)
<img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}"@if ($logo['height']) height="{{ $logo['height'] }}"@endif alt="{{ $senderName }}" style="width: {{ $logo['width'] }}px;@if ($logo['height']) height: {{ $logo['height'] }}px;@endif border: 0;">
@else
<span style="font-size: 22px; font-weight: 700; color: #0a0a0a;">{{ $senderName }}</span>
@endif
</td>
</tr>
</x-slot:header>

{{ $salutation }}

{{ $body }}

Beste Grüße<br>
{{ $signatureName }}@if ($senderName && $senderName !== $signatureName)<br>
{{ $senderName }}@endif

<x-slot:subcopy>
<x-mail::subcopy>
@if ($unsubscribeUrl)
Sie erhalten diese E-Mail als Kunde von {{ $senderName }}. Keine weiteren Infos per E-Mail? [Hier abmelden]({{ $unsubscribeUrl }}).
@else
**Testmail** – an Kunden steht hier der Link zum Abmelden.
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
