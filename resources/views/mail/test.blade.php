@php
    // Own logo in the header, embedded so it shows without loading external images.
    // The view renders twice (HTML + text): reuse the logo if it is already embedded.
    $logo = null;
    if (isset($message) && $logoPath) {
        $embedded = collect($message->getSymfonyMessage()->getAttachments())
            ->first(fn ($part) => $part->getFilename() === basename($logoPath));
        $logo = $embedded ? 'cid:'.$embedded->getContentId() : $message->embed($logoPath);
    }
@endphp
<x-mail::message :logo="$logo">
# Testmail ✓

Wenn du das liest, kommen deine E-Mails an.

<x-mail::panel>
Angefordert: {{ $requestedAt }}<br>
Verschickt: {{ $sentAt }}<br>
Weg: {{ $via }}<br>
Mailer: {{ $mailer }}
</x-mail::panel>

Liegen zwischen „Angefordert“ und „Verschickt“ mehr als ein paar Sekunden, war die Queue langsam oder der Worker ist erst später gelaufen.

@if ($signatureName)
Beste Grüße<br>
{{ $signatureName }}
@endif
</x-mail::message>
