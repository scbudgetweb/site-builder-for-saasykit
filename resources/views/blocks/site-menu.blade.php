{{--
    Theme Builder › Menu: the header links (wide screens) and a menu button opening the mobile menu (below
    $breakpoint), sharing the standard header's partials. Colours override the Branding header tokens for this menu.
--}}
@php
    $menuId = 'site-menu-'.(is_string($uid ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $uid) ? $uid : \Illuminate\Support\Str::random(8));
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" @if ($colourStyle !== '') style="{{ $colourStyle }}" @endif
     @class(['flex items-center', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
    <nav @class(['hidden items-center gap-8', 'lg:flex' => $breakpoint === 'lg', 'md:flex' => $breakpoint === 'md']) aria-label="{{ __('Main') }}">
        @include('site-builder::theme.partials.desktop-links')
    </nav>

    <button type="button" @class(['inline-flex h-10 w-10 items-center justify-center rounded-md text-sb-header-link hover:bg-current/10 focus-visible:bg-current/10', 'lg:hidden' => $breakpoint === 'lg', 'md:hidden' => $breakpoint === 'md'])
            @click="open = true" :aria-expanded="open.toString()" aria-controls="{{ $menuId }}" aria-expanded="false">
        <span class="sr-only">{{ __('Menu') }}</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
    </button>

    @include('site-builder::theme.partials.mobile-menu', ['menuId' => $menuId, 'breakpoint' => $breakpoint, 'showGuestLinks' => false])
</div>
