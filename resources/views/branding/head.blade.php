{{--
    Branding for the public site: icons, self-hosted fonts and the CSS variable overrides. Include it after
    @vite so the overrides win. Every value is validated or whitelisted before it gets here.
--}}
@php
    $branding = app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class);
    $brandingCss = trim($branding->siteCssVariables().' '.app(\ScBudgetWeb\SiteBuilder\Branding\BrandingStyle::class)->css());
    $fonts = $branding->availableFonts();
@endphp

<link rel="icon" href="{{ $branding->faviconUrl() }}">
@if ($appleTouchIcon = $branding->appleTouchIconUrl())
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}">
@endif

@if (isset($fonts[$branding->siteFont()]))
    <link rel="stylesheet" href="{{ $branding->fontStylesheetUrl($branding->siteFont()) }}">
@endif
@if ($headingFont = $branding->siteHeadingFont())
    <link rel="stylesheet" href="{{ $branding->fontStylesheetUrl($headingFont) }}">
@endif

@if ($brandingCss !== '')
    {{-- Unescaped: built only from validated hex colours, font folder names and fixed rules, and entities don't decode inside <style>. --}}
    <style id="site-builder-branding">{!! $brandingCss !!}</style>
@endif

{{-- Admin-written CSS, last so it can override everything else. CustomCss::safe() outputs nothing unless it passes the checks. --}}
@if (($customCss = \ScBudgetWeb\SiteBuilder\Support\CustomCss::safe($branding->string(\ScBudgetWeb\SiteBuilder\Branding\Branding::CUSTOM_CSS))) !== '')
    <style id="site-builder-custom-css">{!! $customCss !!}</style>
@endif
