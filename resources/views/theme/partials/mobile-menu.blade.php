{{--
    The mobile menu (Website › Navigation), shared by the standard header, the Theme Builder's Menu block and the Header
    bar. Opened by an `open` flag in the surrounding x-data. $menuId: its element id; $breakpoint: "lg" or "md", the
    width it hides above (anything else: never hidden); $showGuestLinks: whether it ends with Log in and Get started
    for guests on phones (the standard header). Optionally (the Header bar block) $loginLabel, a Log in link for guests,
    and $cta (label, url, newTab, classes), a button, at the end at every width. $hasOwnColours: the block set mobile
    menu colours of its own, which win over Navigation's background.

    Navigation › Mobile menu chooses how it opens (drops down, slides in from either side over the dimmed page, or fills
    the screen), the links' size, line-up and lines, whether they slide in one after another (never for visitors who
    turn off animations), its background, and what's at the bottom (Log in, the button, social icons, phone and email).
--}}
@php
    $menus = app(\ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::class);
    $mobileMenu = $menus->mobile();
    $openFrom = $mobileMenu['open_from'];
    $isSide = in_array($openFrom, ['left', 'right'], true);
    $isFull = $openFrom === 'full';
    $isCentred = $isFull || $mobileMenu['link_align'] === 'center';
    $linkSize = $isFull && $mobileMenu['link_size'] === 'normal' ? 'large' : $mobileMenu['link_size'];
    $panelStyle = ($hasOwnColours ?? false) ? '' : \ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::mobileStyle($mobileMenu);
    $mobileLinkColors = 'text-sb-header-link transition-colors hover:text-sb-header-link-hover focus-visible:text-sb-header-link-hover';
    $buttonColors = 'bg-sb-header-button text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover focus-visible:bg-sb-header-button-hover focus-visible:text-sb-header-button-text-hover';
    $linkText = match ($linkSize) {
        'xlarge' => 'sb-mm-heading text-3xl font-bold sm:text-4xl',
        'large' => 'sb-mm-heading text-2xl font-semibold',
        default => 'text-lg font-semibold',
    };
    $enterStart = match ($openFrom) {
        'right' => 'translate-x-full',
        'left' => '-translate-x-full',
        'full' => 'opacity-0 scale-95',
        default => '-translate-y-full',
    };
    $socialLinks = $mobileMenu['show_social'] ? \ScBudgetWeb\SiteBuilder\Blocks\Core\SocialFollowBlock::links() : [];
    $showsAccount = $mobileMenu['show_account'] && ($loginLabel ?? null) !== null && \Illuminate\Support\Facades\Route::has('login');
    $showsCta = $mobileMenu['show_cta'] && ($cta ?? null) !== null;
    $hasFooter = $showsAccount || $showsCta || $socialLinks !== [] || $mobileMenu['contact_phone'] !== '' || $mobileMenu['contact_email'] !== '';
@endphp

<div id="{{ $menuId }}" x-show="open" x-cloak
     x-trap.noscroll="open"
     x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}"
     @class(['sb-mobile-menu fixed inset-0 z-[70]', 'lg:hidden' => $breakpoint === 'lg', 'md:hidden' => $breakpoint === 'md'])>
    @if ($isSide)
        {{-- The page behind, dimmed (and blurred); a click on it closes the menu. --}}
        <div @click="open = false" aria-hidden="true" @class(['absolute inset-0 bg-black/40', 'backdrop-blur-sm' => $mobileMenu['blur']])></div>
    @endif

    <div x-show="open"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="{{ $enterStart }}" x-transition:enter-end="translate-x-0 translate-y-0 opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0 translate-y-0 opacity-100 scale-100" x-transition:leave-end="{{ $enterStart }}"
         @if ($panelStyle !== '') style="{{ $panelStyle }}" @endif
         @class([
             'sb-mobile-menu-panel absolute flex flex-col bg-sb-header-bg text-sb-header-text',
             'inset-0' => ! $isSide,
             'inset-y-0 w-full shadow-2xl sm:max-w-sm' => $isSide,
             'right-0' => $openFrom === 'right',
             'left-0' => $openFrom === 'left',
             'sb-mm-animate' => $mobileMenu['animate_links'],
         ])>
        <div @class(['flex min-h-16 shrink-0 items-center justify-between gap-4 px-4 py-3 sm:px-6', 'border-b border-current/10' => ! $isFull])>
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

            {{-- The menu button, now an X: its lines turn as the menu opens. --}}
            <button type="button" @click="open = false" @class([
                'sb-mm-close relative inline-flex h-10 w-10 shrink-0 items-center justify-center hover:bg-current/10 focus-visible:bg-current/10',
                'rounded-full bg-current/10' => $mobileMenu['button_shape'] === 'circle',
                'rounded-sb-button bg-current/10' => $mobileMenu['button_shape'] === 'square',
                'rounded-md' => $mobileMenu['button_shape'] === 'plain',
            ])>
                <span class="sr-only">{{ __('Close menu') }}</span>
                <span class="sb-mm-close-line sb-mm-close-line-1" aria-hidden="true"></span>
                <span class="sb-mm-close-line sb-mm-close-line-2" aria-hidden="true"></span>
            </button>
        </div>

        <nav @class(['flex-1 overflow-y-auto overscroll-contain px-4 pb-8 sm:px-6', 'flex flex-col justify-center' => $isFull, 'pt-2' => $isSide]) aria-label="{{ __('Main') }}">
            {{-- No hover on touch screens, so dropdowns and mega menus open in place. --}}
            @foreach ($menus->header() as $item)
                @php
                    $columns = $item['mega'] ? $item['mega']['columns'] : ($item['children'] !== [] ? [['heading' => '', 'links' => $item['children']]] : []);
                    $holdsCurrentPage = collect($columns)->flatMap(fn (array $column): array => $column['links'])->contains(fn (array $link): bool => $menus->isCurrent($link['url']));
                    $rowClasses = ['sb-mm-item', 'border-b border-current/10' => $mobileMenu['link_lines'] && ! $isFull];
                @endphp

                @if ($columns === [])
                    <a href="{{ $item['url'] }}" @click="open = false" style="--sb-mm-i: {{ $loop->index }}"
                       @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                       @if ($menus->isCurrent($item['url'])) aria-current="page" @endif
                       @class([...$rowClasses, 'block py-4', $linkText, $mobileLinkColors, 'text-center' => $isCentred, '!text-sb-header-link-hover' => $menus->isCurrent($item['url'])])>{{ $item['label'] }}</a>
                @else
                    <div x-data="{ expanded: {{ $holdsCurrentPage ? 'true' : 'false' }} }" style="--sb-mm-i: {{ $loop->index }}" @class($rowClasses)>
                        <div @class(['flex items-center gap-2', 'justify-center' => $isCentred])>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" @click="open = false"
                                   @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                                   @if ($menus->isCurrent($item['url'])) aria-current="page" @endif
                                   @class(['py-4', $linkText, $mobileLinkColors, 'flex-1' => ! $isCentred, '!text-sb-header-link-hover' => $menus->isCurrent($item['url']) || $holdsCurrentPage])>{{ $item['label'] }}</a>
                            @else
                                <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" aria-expanded="{{ $holdsCurrentPage ? 'true' : 'false' }}"
                                        @class(['py-4', $linkText, $mobileLinkColors, 'flex-1 text-left' => ! $isCentred, '!text-sb-header-link-hover' => $holdsCurrentPage])>{{ $item['label'] }}</button>
                            @endif

                            <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" aria-expanded="{{ $holdsCurrentPage ? 'true' : 'false' }}"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md hover:bg-current/5 focus-visible:bg-current/5">
                                <span class="sr-only">{{ __('Show :name links', ['name' => $item['label']]) }}</span>
                                <svg class="h-5 w-5 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>

                        <div x-show="expanded" x-collapse @unless ($holdsCurrentPage) x-cloak @endunless class="pb-4">
                            <div @class(['rounded-sb-card bg-current/5 py-2', 'text-center' => $isCentred])>
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

            @if ($showGuestLinks && $mobileMenu['show_account'])
            @guest
                <div class="sb-mm-item mt-6 flex flex-col gap-2 sm:hidden" style="--sb-mm-i: {{ count($menus->header()) }}">
                    <a href="{{ route('login') }}" @class(['rounded-md px-3 py-3 text-base font-medium text-sb-header-account-link hover:bg-current/5', 'text-center' => $isCentred])>{{ __('Log in') }}</a>
                    @if ($mobileMenu['show_cta'])
                        <a href="{{ route('home') }}#pricing" @click="open = false" class="rounded-sb-button px-4 py-3 text-center text-base font-semibold {{ $buttonColors }}">{{ __('Get started') }}</a>
                    @endif
                </div>
            @endguest
            @endif
        </nav>

        @if ($hasFooter)
            {{-- At the bottom: Log in and the button, then social icons and a contact line. --}}
            <div @class(['sb-mm-item flex shrink-0 flex-col gap-4 border-t border-current/10 px-4 py-5 sm:px-6', 'items-center text-center' => $isCentred]) style="--sb-mm-i: {{ count($menus->header()) + 1 }}">
                @if ($showsAccount || $showsCta)
                    <div @class(['flex w-full flex-col gap-2', 'items-center' => $isCentred])>
                        @if ($showsAccount)
                            @guest
                                <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-base font-medium text-sb-header-account-link hover:bg-current/5">{{ $loginLabel }}</a>
                            @endguest
                        @endif
                        @if ($showsCta)
                            <a href="{{ $cta['url'] }}" @click="open = false" @if ($cta['newTab']) target="_blank" rel="noopener" @endif
                               class="inline-flex w-full justify-center text-center text-base {{ $cta['classes'] }}">{{ $cta['label'] }}</a>
                        @endif
                    </div>
                @endif

                @if ($socialLinks !== [])
                    <div @class(['flex flex-wrap gap-2', 'justify-center' => $isCentred])>
                        @foreach ($socialLinks as $network => $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" title="{{ $link['label'] }}"
                               class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-current/30 transition hover:bg-current/10 [&_svg]:h-4 [&_svg]:w-4 {{ $mobileLinkColors }}">
                                <x-site-builder::social-icon :network="$network" />
                                <span class="sr-only">{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($mobileMenu['contact_phone'] !== '' || $mobileMenu['contact_email'] !== '')
                    <p @class(['flex flex-wrap gap-x-5 gap-y-1 text-sm opacity-90', 'justify-center' => $isCentred])>
                        @if ($mobileMenu['contact_phone'] !== '')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $mobileMenu['contact_phone']) }}" class="underline-offset-4 hover:underline {{ $mobileLinkColors }}">{{ $mobileMenu['contact_phone'] }}</a>
                        @endif
                        @if ($mobileMenu['contact_email'] !== '')
                            <a href="mailto:{{ $mobileMenu['contact_email'] }}" class="underline-offset-4 hover:underline {{ $mobileLinkColors }}">{{ $mobileMenu['contact_email'] }}</a>
                        @endif
                    </p>
                @endif
            </div>
        @endif
    </div>
</div>
