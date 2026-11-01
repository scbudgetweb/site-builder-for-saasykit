{{--
    Theme Builder › Menu: the header links (wide screens) and a menu button opening the mobile menu (below
    $breakpoint), sharing the standard header's partials. Colours override the Branding header tokens for this menu.
    The links and the button (.sb-header-part) take a see-through header's text colours; the mobile menu keeps its own.
--}}
@php
    $menuId = 'site-menu-'.(is_string($uid ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $uid) ? $uid : \Illuminate\Support\Str::random(8));
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" @if ($colourStyle !== '') style="{{ $colourStyle }}" @endif
     @class(['flex items-center', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
    <nav @class(['sb-header-part hidden items-center gap-8', 'lg:flex' => $breakpoint === 'lg', 'md:flex' => $breakpoint === 'md']) aria-label="{{ __('Main') }}">
        @include('site-builder::theme.partials.desktop-links')
    </nav>

    @include('site-builder::theme.partials.menu-button', ['menuId' => $menuId, 'class' => $breakpoint === 'md' ? 'sb-header-part inline-flex md:hidden text-sb-header-link' : 'sb-header-part inline-flex lg:hidden text-sb-header-link'])

    @include('site-builder::theme.partials.mobile-menu', ['menuId' => $menuId, 'breakpoint' => $breakpoint, 'showGuestLinks' => false, 'hasOwnColours' => $hasOwnMobileColours])
</div>
