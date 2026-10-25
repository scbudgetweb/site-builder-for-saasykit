@props([
    // "hero" (home page) or "inner" (every other page with a banner): which Branding config to use.
    'variant' => 'inner',
    // A block's own image; wins over the config.
    'imageUrl' => null,
    // With imageUrl: ['color' => hex, 'opacity' => 0-1].
    'imageOverlay' => null,
    // With imageUrl: center / top / bottom.
    'imagePosition' => null,
])

{{--
    Just the background of a Branding banner, as an absolutely positioned layer: image + overlay, a custom gradient,
    one colour, or the standard gradient in the site colour. Put it inside a "relative" element. The image is clipped
    in its own layer, so content in the parent can still hang over the next section (lesson 8).
--}}
@php
    $banner = app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class)->banner($variant, $imageUrl, $imageOverlay, $imagePosition);
    // Built only from validated hex colours and whitelisted angles.
    $style = $banner['gradient'] !== null ? "background-image: {$banner['gradient']};" : ($banner['color'] !== null ? "background-color: {$banner['color']};" : '');
@endphp

<div {{ $attributes->class([
    'absolute inset-0 overflow-hidden rounded-[inherit]',
    'bg-gradient-to-br from-primary-900 via-primary-800 to-primary-950' => $banner['gradient'] === null && $banner['color'] === null,
]) }} @if ($style !== '') style="{{ $style }}" @endif aria-hidden="true">
    @if ($banner['image'])
        <img src="{{ $banner['image'] }}" alt="" @if ($variant === 'hero') fetchpriority="high" @else loading="lazy" @endif decoding="async"
             class="absolute inset-0 h-full w-full object-cover"
             style="object-position: {{ $banner['imagePosition'] }};">

        @if ($banner['overlay'] && $banner['overlay']['opacity'] > 0)
            <div class="absolute inset-0" style="background-color: {{ $banner['overlay']['color'] }}; opacity: {{ $banner['overlay']['opacity'] }};"></div>
        @endif
    @endif
</div>
