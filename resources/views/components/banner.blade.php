@props([
    'variant' => 'inner',
    'imageUrl' => null,
    'imageOverlay' => null,
    'imagePosition' => null,
    // It opens the page, so a header set to sit over the first section does (Templates\HeaderBehaviour).
    'opening' => false,
])

{{--
    A full-width banner with the Branding background (see banner-background). Its content should use
    text-sb-banner-heading / text-sb-banner-subtitle and sits above the background.
    No overflow-hidden here: content such as dropdowns must be able to hang over the next section (lesson 8).
    An opening banner starts with room for a header over it (.sb-header-space: no height unless there is one).
--}}
@php
    if ($opening) {
        request()->attributes->set(\ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour::OPENING_BANNER_ATTRIBUTE, true);
    }

    // An app's own banner ("app_{key}", from SiteBuilder::brandingSection()) uses its text colours, or the inner banner's.
    $textVariant = $variant === 'hero' ? 'hero' : (in_array($variant, app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class)->banners(), true) ? $variant : 'inner');
    $fallback = fn (string $text): string => in_array($textVariant, ['hero', 'inner'], true) ? '' : ", var(--color-sb-inner-{$text})";
@endphp

<section {{ $attributes->class(['relative']) }}
         style="--color-sb-banner-heading: var(--color-sb-{{ $textVariant }}-heading{{ $fallback('heading') }}); --color-sb-banner-subtitle: var(--color-sb-{{ $textVariant }}-subtitle{{ $fallback('subtitle') }});">
    <x-site-builder::banner-background :variant="$textVariant" :image-url="$imageUrl" :image-overlay="$imageOverlay" :image-position="$imagePosition" />

    <div class="relative">
        @if ($opening)
            <div class="sb-header-space" aria-hidden="true"></div>
        @endif
        {{ $slot }}
    </div>
</section>
