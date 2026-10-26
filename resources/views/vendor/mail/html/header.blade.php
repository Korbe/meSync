@props(['url', 'logo' => null, 'alt' => 'Korbitsch'])
{{-- logo: from App\Support\MailLogo::embed() (own logo, or the korbitsch.at one for mails to me) --}}
<tr>
<td class="header">
@if ($logo)
@include('mail.partials.logo', ['logo' => $logo, 'alt' => $alt])
@else
{{-- Preview without a message to embed into: the public file --}}
<img src="{{ asset('images/mail/korbitsch.png') }}" width="170" height="55" alt="Korbitsch" style="width: 170px; height: 55px; border: 0;">
@endif
</td>
</tr>
