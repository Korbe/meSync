@props(['url', 'logo' => null, 'logos' => []])
{{-- logo: from App\Support\MailLogo::embed() (src, width, height) --}}
@php
    // Own logo from the profile when the mail passes it, otherwise the korbitsch.at logo.
    // Embedded (cid:) images when available, otherwise the public URL.
    $korbitschLogo = $logos['korbitsch'] ?? asset('images/mail/korbitsch.png');
@endphp
<tr>
<td class="header">
@if ($logo)
<img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}"@if ($logo['height']) height="{{ $logo['height'] }}"@endif alt="Logo" style="width: {{ $logo['width'] }}px;@if ($logo['height']) height: {{ $logo['height'] }}px;@endif border: 0;">
@else
<a href="https://korbitsch.at" style="text-decoration: none;">
<img src="{{ $korbitschLogo }}" width="170" height="39" alt="Korbitsch" class="brand-partner" style="width: 170px; height: 39px; border: 0;">
</a>
@endif
</td>
</tr>
