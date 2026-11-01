@props([
    // Which Branding logo choice to use: header, footer, auth or dashboards.
    'place' => 'header',
    // Classes for the site-name fallback (when that logo isn't uploaded).
    'nameClass' => '',
])

{{-- The logo chosen for this place in Branding, or the site name when that logo hasn't been uploaded. --}}
@php
    $branding = app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class);
    $key = 'branding.logo_'.(in_array($place, ['header', 'footer', 'auth', 'dashboards'], true) ? $place : 'header');
    $url = $branding->logoUrlFor($key);
@endphp

@if ($url)
    {{-- .sb-logo: a header that shrinks makes it smaller, and one over the first section can show the other logo. --}}
    <img src="{{ $url }}" alt="{{ config('app.name') }}" {{ $attributes->class(['sb-logo h-8 w-auto']) }}>
@else
    <span {{ $attributes->class(['sb-site-name text-xl font-bold tracking-tight', $nameClass]) }}>{{ config('app.name') }}</span>
@endif
