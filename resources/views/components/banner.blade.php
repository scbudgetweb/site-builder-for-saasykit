@props([
    'variant' => 'inner',
    'imageUrl' => null,
    'imageOverlay' => null,
    'imagePosition' => null,
])

{{--
    A full-width banner with the Branding background (see banner-background). Its content should use
    text-sb-banner-heading / text-sb-banner-subtitle and sits above the background.
    No overflow-hidden here: content such as dropdowns must be able to hang over the next section (lesson 8).
--}}
@php
    // An app's own banner ("app_{key}", from SiteBuilder::brandingSection()) uses its text colours, or the inner banner's.
    $textVariant = $variant === 'hero' ? 'hero' : (in_array($variant, app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class)->banners(), true) ? $variant : 'inner');
    $fallback = fn (string $text): string => in_array($textVariant, ['hero', 'inner'], true) ? '' : ", var(--color-sb-inner-{$text})";
@endphp

<section {{ $attributes->class(['relative']) }}
         style="--color-sb-banner-heading: var(--color-sb-{{ $textVariant }}-heading{{ $fallback('heading') }}); --color-sb-banner-subtitle: var(--color-sb-{{ $textVariant }}-subtitle{{ $fallback('subtitle') }});">
    <x-site-builder::banner-background :variant="$textVariant" :image-url="$imageUrl" :image-overlay="$imageOverlay" :image-position="$imagePosition" />

    <div class="relative">
        {{ $slot }}
    </div>
</section>
