{{--
    The mobile menu (Website › Navigation), shared by the standard header and the Theme Builder's Menu block. Opened by
    an `open` flag in the surrounding x-data. $menuId: its element id; $breakpoint: "lg" or "md", the width it hides
    above; $showGuestLinks: whether it ends with Log in and Get started for guests.
--}}
@php
    $menus = app(\ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::class);
    $mobileMenu = $menus->mobile();
    $mobileFromRight = $mobileMenu['open_from'] === 'right';
    $mobileLinkColors = 'text-sb-header-link transition-colors hover:text-sb-header-link-hover focus-visible:text-sb-header-link-hover';
    $buttonColors = 'bg-sb-header-button text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover focus-visible:bg-sb-header-button-hover focus-visible:text-sb-header-button-text-hover';
@endphp
{{--
    Mobile menu (below lg): covers the screen and scrolls on its own, opening from the top or the right
    (Website › Navigation › Mobile menu), with the header logo and an optional tagline at the top. Dropdowns and
    mega menus open and close in place; one holding the current page starts open.
--}}
<div id="{{ $menuId }}" x-show="open" x-cloak
     x-trap.noscroll="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="{{ $mobileFromRight ? 'translate-x-full' : '-translate-y-full' }}"
     x-transition:enter-end="translate-x-0 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="translate-x-0 translate-y-0"
     x-transition:leave-end="{{ $mobileFromRight ? 'translate-x-full' : '-translate-y-full' }}"
     role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}"
     @class(['fixed inset-0 z-[70] flex flex-col bg-sb-header-bg text-sb-header-text', 'lg:hidden' => $breakpoint === 'lg', 'md:hidden' => $breakpoint === 'md'])>
    <div class="flex min-h-16 shrink-0 items-center justify-between gap-4 border-b border-current/10 px-4 py-3 sm:px-6">
        @if ($mobileMenu['show_logo'])
            <a href="{{ route('home') }}" class="min-w-0">
                <x-site-builder::logo place="header" />
                @if ($mobileMenu['tagline'] !== '')
                    <span class="mt-2 block text-xs font-semibold uppercase tracking-widest opacity-70">{{ $mobileMenu['tagline'] }}</span>
                @endif
            </a>
        @else
            <span></span>
        @endif

        <button type="button" @click="open = false" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md hover:bg-current/10 focus-visible:bg-current/10">
            <span class="sr-only">{{ __('Close menu') }}</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto overscroll-contain px-4 pb-8 sm:px-6" aria-label="{{ __('Main') }}">
        {{-- No hover on touch screens, so dropdowns and mega menus open in place. --}}
        @foreach ($menus->header() as $item)
            @php
                $columns = $item['mega'] ? $item['mega']['columns'] : ($item['children'] !== [] ? [['heading' => '', 'links' => $item['children']]] : []);
                $holdsCurrentPage = collect($columns)->flatMap(fn (array $column): array => $column['links'])->contains(fn (array $link): bool => $menus->isCurrent($link['url']));
            @endphp

            @if ($columns === [])
                <a href="{{ $item['url'] }}" @click="open = false"
                   @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                   @if ($menus->isCurrent($item['url'])) aria-current="page" @endif
                   @class(['block border-b border-current/10 py-4 text-lg font-semibold', $mobileLinkColors, '!text-sb-header-link-hover' => $menus->isCurrent($item['url'])])>{{ $item['label'] }}</a>
            @else
                <div x-data="{ expanded: {{ $holdsCurrentPage ? 'true' : 'false' }} }" class="border-b border-current/10">
                    <div class="flex items-center gap-2">
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" @click="open = false"
                               @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                               @if ($menus->isCurrent($item['url'])) aria-current="page" @endif
                               @class(['flex-1 py-4 text-lg font-semibold', $mobileLinkColors, '!text-sb-header-link-hover' => $menus->isCurrent($item['url']) || $holdsCurrentPage])>{{ $item['label'] }}</a>
                        @else
                            <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" aria-expanded="{{ $holdsCurrentPage ? 'true' : 'false' }}"
                                    @class(['flex-1 py-4 text-left text-lg font-semibold', $mobileLinkColors, '!text-sb-header-link-hover' => $holdsCurrentPage])>{{ $item['label'] }}</button>
                        @endif

                        <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" aria-expanded="{{ $holdsCurrentPage ? 'true' : 'false' }}"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md hover:bg-current/5 focus-visible:bg-current/5">
                            <span class="sr-only">{{ __('Show :name links', ['name' => $item['label']]) }}</span>
                            <svg class="h-5 w-5 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>

                    <div x-show="expanded" x-collapse @unless ($holdsCurrentPage) x-cloak @endunless class="pb-4">
                        <div class="rounded-sb-card bg-current/5 py-2">
                            @foreach ($columns as $column)
                                @if ($column['heading'] !== '')
                                    <p class="px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-wider opacity-70">{{ $column['heading'] }}</p>
                                @endif
                                @foreach ($column['links'] as $child)
                                    <a href="{{ $child['url'] }}" @click="open = false"
                                       @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                                       @if ($menus->isCurrent($child['url'])) aria-current="page" @endif
                                       @class(['block px-4 py-2.5 text-base font-medium', $mobileLinkColors, '!text-sb-header-link-hover' => $menus->isCurrent($child['url'])])>{{ $child['label'] }}</a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        @if ($showGuestLinks)
        @guest
            <div class="mt-6 flex flex-col gap-2 sm:hidden">
                <a href="{{ route('login') }}" class="rounded-md px-3 py-3 text-base font-medium text-sb-header-account-link hover:bg-current/5">{{ __('Log in') }}</a>
                <a href="{{ route('home') }}#pricing" @click="open = false" class="rounded-sb-button px-4 py-3 text-center text-base font-semibold {{ $buttonColors }}">{{ __('Get started') }}</a>
            </div>
        @endguest
        @endif
    </nav>
</div>
