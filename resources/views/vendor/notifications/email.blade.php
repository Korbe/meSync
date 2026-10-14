@php
    // Embed the logos (cid:) so they show even when the app URL isn't public.
    // The view renders twice (HTML + text), so reuse already embedded images.
    $logos = [];

    if (isset($message)) {
        $embed = function (string $file) use ($message) {
            $embedded = collect($message->getSymfonyMessage()->getAttachments())
                ->first(fn ($part) => $part->getFilename() === $file);

            return $embedded
                ? 'cid:'.$embedded->getContentId()
                : $message->embed(public_path('images/mail/'.$file));
        };

        $logos = [
            'mesync' => $embed('mesync.png'),
            'korbitsch' => $embed('korbitsch.png'),
        ];
    }
@endphp
<x-mail::message :logos="$logos">
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
