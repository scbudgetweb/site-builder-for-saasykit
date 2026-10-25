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
@php($textVariant = $variant === 'hero' ? 'hero' : 'inner')

<section {{ $attributes->class(['relative']) }}
         style="--color-sb-banner-heading: var(--color-sb-{{ $textVariant }}-heading); --color-sb-banner-subtitle: var(--color-sb-{{ $textVariant }}-subtitle);">
    <x-site-builder::banner-background :variant="$textVariant" :image-url="$imageUrl" :image-overlay="$imageOverlay" :image-position="$imagePosition" />

    <div class="relative">
        {{ $slot }}
    </div>
</section>
