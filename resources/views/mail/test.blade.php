@php
    // Own logo in the header: embedded, with a fixed size (Outlook ignores max-width)
    $logo = \App\Support\MailLogo::embed($message ?? null, $logoPath);
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
