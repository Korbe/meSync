@props(['url', 'logo' => null, 'logos' => []])
@php
    // Own logo from the profile when the mail passes it, otherwise the korbitsch.at logo.
    // Embedded (cid:) images when available, otherwise the public URL.
    $korbitschLogo = $logos['korbitsch'] ?? asset('images/mail/korbitsch.png');
@endphp
<tr>
<td class="header">
@if ($logo)
<img src="{{ $logo }}" alt="Logo" style="max-height: 64px; max-width: 220px;">
@else
<a href="https://korbitsch.at" style="text-decoration: none;">
<img src="{{ $korbitschLogo }}" width="170" alt="Korbitsch" class="brand-partner" style="width: 170px;">
</a>
@endif
</td>
</tr>
