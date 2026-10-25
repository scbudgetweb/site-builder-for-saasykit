{{--
    Site builder theme: shadows SaaSykit's components/layouts/partials/social-cards.blade.php.
    Changes: the share image falls back to Branding › Brand › Share image before SaaSykit's standard cards, og:url is
    the page's own (canonical) address, and there's no "SaaSykit" fallback text. Check against SaaSykit's original
    after upstream merges.
--}}
@php
    $cardTitle = ! empty($title) ? trim((string) $title) : config('app.name');
    $cardDescription = ! empty($description) ? trim((string) $description) : config('app.description');
    $cardImage = ! empty($socialCard) ? trim((string) $socialCard) : app(\ScBudgetWeb\SiteBuilder\Branding\Branding::class)->shareImageUrl();
@endphp

<meta name="twitter:title" content="{{ $cardTitle }}" />
<meta name="twitter:description" content="{{ $cardDescription }}" />

@if (config('open-graphy.enabled', false) && !empty($title) && !request()->routeIs('home'))
    @if ($cardImage)
        <x-open-graphy::links title="{{ $cardTitle }}" image="{{ $cardImage }}"/>
    @else
        <x-open-graphy::links title="{{ $cardTitle }}"/>
    @endif
@else
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ $cardImage ?? asset('images/twitter-card.png') }}" />
    <meta property="og:image" content="{{ $cardImage ?? asset('images/facebook-card.png') }}" />
@endif

<meta property="og:title" content="{{ $cardTitle }}" />
<meta property="og:url" content="{{ isset($canonical) ? trim((string) $canonical) : url()->current() }}" />

<meta property="og:description" content="{{ $cardDescription }}" />
