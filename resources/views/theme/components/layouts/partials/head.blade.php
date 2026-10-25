{{--
    Site builder theme: shadows SaaSykit's components/layouts/partials/head.blade.php.
    Changes: no Google Fonts (fonts are self-hosted, GDPR), and the favicon, Apple touch icon, fonts and colour
    overrides come from Branding (site-builder::branding.head). Check against SaaSykit's original after upstream merges.
--}}
@php($description = isset($description) ? $description : config('app.description'))
<meta name="description" content="{{ $description }}">

@php($canonical = isset($canonical) ? $canonical : url()->current())
<link rel="canonical" href="{{ $canonical }}">

<title>
    @isset($title)
        {{ $title }} | {{ config('app.name') }}
    @else
        {{ config('app.name') }}
    @endisset
</title>

@include('components.layouts.partials.social-cards')

@vite(['resources/css/app.css'])

@include('site-builder::branding.head')

@stack('head')

@livewireStyles
