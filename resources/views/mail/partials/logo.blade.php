{{--
    Logo in the mail header (see App\Support\MailLogo): $logo = ['src', 'width', 'height', 'dark', 'mso'], $alt.
    Classic Outlook (mso) gets the logo on a solid background, all others the transparent one;
    the one with light lettering takes over in dark mode (CSS in the layout: .logo-light / .logo-dark).
--}}
@php
    $box = fn ($image) => 'width="'.$image['width'].'"'.($image['height'] ? ' height="'.$image['height'].'"' : '');
    $boxStyle = fn ($image) => 'width: '.$image['width'].'px;'.($image['height'] ? ' height: '.$image['height'].'px;' : '');
@endphp
@if (! empty($logo['mso']))
<!--[if mso]><img src="{{ $logo['mso'] }}" {!! $box($logo) !!} alt="{{ $alt }}" style="{{ $boxStyle($logo) }} border: 0;"><![endif]-->
<!--[if !mso]><!-->
@endif
<img src="{{ $logo['src'] }}" {!! $box($logo) !!} alt="{{ $alt }}" class="logo-light" style="{{ $boxStyle($logo) }} border: 0;">
@if (! empty($logo['dark']))
<img src="{{ $logo['dark']['src'] }}" {!! $box($logo['dark']) !!} alt="{{ $alt }}" class="logo-dark" style="display: none; max-height: 0; overflow: hidden; {{ $boxStyle($logo['dark']) }} border: 0;">
@endif
@if (! empty($logo['mso']))
<!--<![endif]-->
@endif
