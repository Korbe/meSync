{{--
    Logo in the mail header (see App\Support\MailLogo): $logo = ['src', 'width', 'height', 'dark'], $alt.
    The one with light lettering takes over in dark mode (CSS in the layout: .logo-light / .logo-dark).
    Classic Outlook ignores display/mso-hide on images, only on blocks: the dark one sits in a
    hidden div. It stays in the HTML, unused embedded images would show up there as attachments.
--}}
@php
    $box = fn ($image) => 'width="'.$image['width'].'"'.($image['height'] ? ' height="'.$image['height'].'"' : '');
    $boxStyle = fn ($image) => 'width: '.$image['width'].'px;'.($image['height'] ? ' height: '.$image['height'].'px;' : '');
@endphp
<img src="{{ $logo['src'] }}" {!! $box($logo) !!} alt="{{ $alt }}" class="logo-light" style="{{ $boxStyle($logo) }} border: 0;">
@if (! empty($logo['dark']))
<div class="logo-dark" style="display: none; mso-hide: all; max-height: 0; overflow: hidden; line-height: 0; font-size: 0;">
<img src="{{ $logo['dark']['src'] }}" {!! $box($logo['dark']) !!} alt="{{ $alt }}" style="{{ $boxStyle($logo['dark']) }} border: 0;">
</div>
@endif
