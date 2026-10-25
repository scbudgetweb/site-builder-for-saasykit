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
    <img src="{{ $url }}" alt="{{ config('app.name') }}" {{ $attributes->class(['h-8 w-auto']) }}>
@else
    <span {{ $attributes->class(['text-xl font-bold tracking-tight', $nameClass]) }}>{{ config('app.name') }}</span>
@endif
