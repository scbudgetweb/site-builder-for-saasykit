{{--
    A legal page's text (Website › Legal pages): the saved content, with "Last updated", or the built-in text until
    content is saved. The theme's Privacy Policy and Terms of Service use it; an app shows its own legal pages with
    <x-site-builder::legal-page key="cookie_policy" />.
--}}
@props(['key'])

@php
    $legalPages = app(\ScBudgetWeb\SiteBuilder\Legal\LegalPages::class);
    $legalPage = $legalPages->find($key);
@endphp

@if ($legalPage)
    @if ($legalPages->hasCustomContent($legalPage))
        @if ($lastUpdated = $legalPages->lastUpdated($legalPage))
            <p class="mb-6 text-sm opacity-70">{{ __('Last updated: :date', ['date' => $lastUpdated->translatedFormat(config('app.date_format', 'j F Y'))]) }}</p>
        @endif

        <article {{ $attributes->class(['blog-post']) }}>
            {!! $legalPages->html($legalPage) !!}
        </article>
    @elseif ($legalPage->defaultView())
        <article {{ $attributes->class(['blog-post']) }}>
            @include($legalPage->defaultView())
        </article>
    @endif
@endif
