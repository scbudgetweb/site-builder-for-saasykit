{{--
    Site builder theme: shadows SaaSykit's components/layouts/app/header.blade.php.
    Colours come from Branding › Header & footer; the logo from Branding › Brand (or the site name); the links from
    Website › Navigation. The account area stays automatic: Log in / Get started for guests, SaaSykit's user menu when
    signed in.

    A Theme Builder header template (Website › Theme builder) that applies to this page takes its place; the
    announcement bar still shows above it.
--}}
@php
    $headerTemplate = app(\ScBudgetWeb\SiteBuilder\Templates\Templates::class)->render('header');
@endphp

@if ($headerTemplate !== null)
    <header {{ $attributes->class(['relative z-40']) }}>
        @unless (app(\ScBudgetWeb\SiteBuilder\Templates\Templates::class)->hidesChrome())
            <livewire:announcement.view />
        @endunless
        {{ $headerTemplate }}
    </header>
@else
@php
    $buttonColors = 'bg-sb-header-button text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover focus-visible:bg-sb-header-button-hover focus-visible:text-sb-header-button-text-hover';
@endphp

<header {{ $attributes->class(['relative z-40 bg-sb-header-bg text-sb-header-text border-b border-current/10']) }}
        x-data="{ open: false }" @keydown.escape.window="open = false">
    <livewire:announcement.view />

    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
            <x-site-builder::logo place="header" />
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="{{ __('Main') }}">
            <x-layouts.app.navigation-links />
        </nav>

        <div class="flex items-center gap-3">
            @auth
                <x-layouts.app.user-menu />
            @else
                <a href="{{ route('login') }}" class="hidden font-medium text-sb-header-account-link transition-colors hover:text-sb-header-account-link-hover focus-visible:text-sb-header-account-link-hover sm:inline">{{ __('Log in') }}</a>
                <a href="{{ route('home') }}#pricing" class="hidden rounded-sb-button px-4 py-2 font-semibold sm:inline-flex {{ $buttonColors }}">{{ __('Get started') }}</a>
            @endauth

            @include('site-builder::theme.partials.menu-button', ['menuId' => 'site-mobile-menu', 'class' => 'inline-flex lg:hidden'])
        </div>
    </div>

    @include('site-builder::theme.partials.mobile-menu', ['menuId' => 'site-mobile-menu', 'breakpoint' => 'lg', 'showGuestLinks' => true])
</header>
@endif
