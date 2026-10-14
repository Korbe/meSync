@props(['url', 'logos' => []])
@php
    // Embedded (cid:) images when available, otherwise the public URLs
    $mesyncLogo = $logos['mesync'] ?? asset('images/mail/mesync.png');
    $korbitschLogo = $logos['korbitsch'] ?? asset('images/mail/korbitsch.png');
@endphp
<tr>
<td class="header">
{{-- meSync + korbitsch.at, shown like an integration --}}
<table class="brand" align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-app" valign="middle">
<a href="{{ $url }}" style="text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td valign="middle"><img src="{{ $mesyncLogo }}" width="40" height="40" alt="meSync" class="brand-mark"></td>
<td valign="middle" class="brand-name">me<span class="brand-name-accent">Sync</span></td>
</tr>
</table>
</a>
</td>
<td class="brand-plus-cell" valign="middle">
<div class="brand-plus">+</div>
</td>
<td valign="middle">
<a href="https://korbitsch.at" style="text-decoration: none;">
<img src="{{ $korbitschLogo }}" width="150" alt="Korbitsch" class="brand-partner">
</a>
</td>
</tr>
</table>
</td>
</tr>
