{{--
    Theme Builder › Header bar: the logo, the header links, the account links and a button, laid out by $headerStyle,
    with a menu button opening the mobile menu below $breakpoint ("always" for the menu-button-only style). Below the
    breakpoint the links and account links move into the mobile menu, and the button too unless it stays on phones.
    Colours override the Branding header tokens for this header.
--}}
@php
    $menuId = 'site-header-'.(is_string($uid ?? null) && preg_match('/^[A-Za-z0-9_-]+$/', $uid) ? $uid : \Illuminate\Support\Str::random(8));
    $isAlways = $breakpoint === 'always';
    // Shown from the breakpoint up (never for the menu-button-only style).
    $wide = $isAlways ? 'hidden' : ($breakpoint === 'md' ? 'hidden md:flex' : 'hidden lg:flex');
    $wideInline = $isAlways ? 'hidden' : ($breakpoint === 'md' ? 'hidden md:inline-flex' : 'hidden lg:inline-flex');
    $narrow = $isAlways ? 'inline-flex' : ($breakpoint === 'md' ? 'inline-flex md:hidden' : 'inline-flex lg:hidden');
    $hasActions = $showsAccount || $cta !== null;
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false"
     @class(['sb-header-bar', 'bg-sb-header-bg text-sb-header-text' => $hasHeaderBackground, 'border-b border-current/10' => $hasBorder])
     @if ($colourStyle !== '') style="{{ $colourStyle }}" @endif>
    <div @class(['mx-auto px-4 sm:px-6 lg:px-8', $widthClasses])>

        @php
            $logo = $showsLogo ? view('site-builder::blocks.partials.site-header-logo', ['logoStyle' => $logoStyle]) : null;
            $actions = view('site-builder::blocks.partials.site-header-actions', compact('showsAccount', 'loginLabel', 'hasUserMenu', 'cta', 'wide', 'wideInline', 'narrow', 'menuId', 'isAlways'));
        @endphp

        @if ($headerStyle === 'centred_logo')
            <div @class(['grid grid-cols-[1fr_auto_1fr] items-center gap-4 py-2', $heightClasses])>
                <div></div>
                <div class="flex justify-center">{{ $logo }}</div>
                <div class="flex items-center justify-end gap-3">{{ $actions }}</div>
            </div>
            @if ($links !== [])
                <nav @class([$wide, 'items-center justify-center gap-8 border-t border-current/10 py-3']) aria-label="{{ __('Main') }}">
                    @include('site-builder::theme.partials.desktop-links', ['items' => $links])
                </nav>
            @endif
        @elseif ($headerStyle === 'split')
            <div @class(['grid grid-cols-[1fr_auto_1fr] items-center gap-6 py-2', $heightClasses])>
                <nav @class([$wide, 'items-center justify-end gap-8']) aria-label="{{ __('Main') }}">
                    @include('site-builder::theme.partials.desktop-links', ['items' => $linkHalves[0] ?? []])
                </nav>
                <div class="col-start-2 flex justify-center">{{ $logo }}</div>
                <div class="col-start-3 flex items-center justify-end gap-8">
                    <nav @class([$wide, 'mr-auto items-center gap-8']) aria-label="{{ __('More') }}">
                        @include('site-builder::theme.partials.desktop-links', ['items' => $linkHalves[1] ?? []])
                    </nav>
                    <div class="flex items-center gap-3">{{ $actions }}</div>
                </div>
            </div>
        @elseif ($headerStyle === 'links_centre')
            <div @class(['grid grid-cols-[1fr_auto_1fr] items-center gap-6 py-2', $heightClasses])>
                <div class="flex">{{ $logo }}</div>
                <nav @class([$wide, 'items-center justify-center gap-8']) aria-label="{{ __('Main') }}">
                    @include('site-builder::theme.partials.desktop-links', ['items' => $links])
                </nav>
                <div class="col-start-3 flex items-center justify-end gap-3">{{ $actions }}</div>
            </div>
        @else
            {{-- Logo on the left, links on the right; or, menu button only, the logo and the button. --}}
            <div @class(['flex items-center justify-between gap-6 py-2', $heightClasses])>
                <div class="flex">{{ $logo }}</div>
                <div class="flex items-center gap-8">
                    @if ($links !== [] && ! $isAlways)
                        <nav @class([$wide, 'items-center gap-8']) aria-label="{{ __('Main') }}">
                            @include('site-builder::theme.partials.desktop-links', ['items' => $links])
                        </nav>
                    @endif
                    <div class="flex items-center gap-3">{{ $actions }}</div>
                </div>
            </div>
        @endif
    </div>

    @include('site-builder::theme.partials.mobile-menu', [
        'menuId' => $menuId,
        'breakpoint' => $breakpoint,
        'showGuestLinks' => false,
        'loginLabel' => $showsAccount ? $loginLabel : null,
        'cta' => $cta,
        'hasOwnColours' => $hasOwnMobileColours,
    ])
</div>
