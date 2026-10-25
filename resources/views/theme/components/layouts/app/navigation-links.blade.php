{{--
    Site builder theme: shadows SaaSykit's components/layouts/app/navigation-links.blade.php.
    Desktop header links from Admin › Website › Navigation. A link with children opens a dropdown, and a mega menu item
    opens a full-width panel of link columns (positioned against the header). Both open on hover, and on click or
    keyboard through the arrow button; Escape or clicking elsewhere closes them. Panel colours are the header-menu
    tokens from Branding › Header & footer.
--}}
@php
    $menus = app(\ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::class);
    $linkColors = 'text-sb-header-link transition-colors hover:text-sb-header-link-hover focus-visible:text-sb-header-link-hover';
    $menuLinkColors = 'text-sb-header-menu-link transition-colors hover:bg-current/5 hover:text-sb-header-menu-link-hover focus-visible:bg-current/5 focus-visible:text-sb-header-menu-link-hover';
@endphp

@foreach ($menus->header() as $item)
    @php
        $menuLinks = $item['mega'] ? collect($item['mega']['columns'])->flatMap(fn (array $column): array => $column['links']) : collect($item['children']);
        $isCurrent = $menus->isCurrent($item['url']) || $menuLinks->contains(fn (array $child): bool => $menus->isCurrent($child['url']));
    @endphp

    @if ($item['children'] === [] && $item['mega'] === null)
        <a href="{{ $item['url'] }}"
           @if ($item['new_tab']) target="_blank" rel="noopener" @endif
           @if ($isCurrent) aria-current="page" @endif
           @class(['font-medium', $linkColors, '!text-sb-header-link-hover' => $isCurrent])>{{ $item['label'] }}</a>
    @else
        {{-- A mega menu's trigger fills the header's height, so the mouse can move straight onto the panel below. --}}
        <div @class(['relative' => ! $item['mega'], 'flex h-16 items-center' => $item['mega']]) x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
             @keydown.escape.stop="open = false" @click.outside="open = false">
            <div class="flex items-center gap-1">
                @if ($item['url'])
                    <a href="{{ $item['url'] }}"
                       @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                       @if ($menus->isCurrent($item['url'])) aria-current="page" @endif
                       @class(['font-medium', $linkColors, '!text-sb-header-link-hover' => $isCurrent])>{{ $item['label'] }}</a>
                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false"
                            @class(['-mr-1 rounded p-1', $linkColors, '!text-sb-header-link-hover' => $isCurrent])>
                        <span class="sr-only">{{ __('Show :name links', ['name' => $item['label']]) }}</span>
                        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                @else
                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false"
                            @class(['inline-flex items-center gap-1 font-medium', $linkColors, '!text-sb-header-link-hover' => $isCurrent])>
                        {{ $item['label'] }}
                        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                @endif
            </div>

            @if ($item['mega'])
                @php
                    $panelCount = count($item['mega']['columns']) + ($item['mega']['promo'] ? 1 : 0);
                @endphp
                <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute inset-x-0 top-full z-50">
                    <div class="border-t border-gray-900/5 bg-sb-header-menu-bg shadow-lg">
                        <div @class([
                            'mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:px-8',
                            'grid-cols-2' => $panelCount === 2,
                            'grid-cols-3' => $panelCount === 3,
                            'grid-cols-4' => $panelCount === 4,
                            'grid-cols-5' => $panelCount === 5,
                        ])>
                            @foreach ($item['mega']['columns'] as $column)
                                <div class="min-w-0">
                                    @if ($column['heading'] !== '')
                                        <p class="px-2 text-xs font-semibold uppercase tracking-wider text-sb-header-menu-heading">{{ $column['heading'] }}</p>
                                    @endif
                                    <ul @class(['space-y-1', 'mt-3' => $column['heading'] !== ''])>
                                        @foreach ($column['links'] as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}"
                                                   @if ($link['new_tab']) target="_blank" rel="noopener" @endif
                                                   @if ($menus->isCurrent($link['url'])) aria-current="page" @endif
                                                   @class(['group flex gap-3 rounded-lg p-2', $menuLinkColors, '!text-sb-header-menu-link-hover' => $menus->isCurrent($link['url'])])>
                                                    @if ($link['icon'])
                                                        <span class="mt-0.5 shrink-0 text-sb-header-menu-link-hover">{{ \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($link['icon'], 'h-5 w-5') }}</span>
                                                    @endif
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold">{{ $link['label'] }}</span>
                                                        @if ($link['description'])
                                                            <span class="mt-0.5 block text-sm text-sb-header-menu-heading">{{ $link['description'] }}</span>
                                                        @endif
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach

                            @if ($promo = $item['mega']['promo'])
                                <div class="rounded-sb-card bg-primary-50 p-6 text-gray-900">
                                    @if ($promo['heading'] !== '')
                                        <p class="text-base font-semibold">{{ $promo['heading'] }}</p>
                                    @endif
                                    @if ($promo['text'] !== '')
                                        <p class="mt-2 text-sm text-gray-600">{{ $promo['text'] }}</p>
                                    @endif
                                    @if ($promo['button_url'])
                                        <a href="{{ $promo['button_url'] }}" class="site-button site-button-1 mt-4 px-4 py-2 text-sm font-semibold">{{ $promo['button_label'] }}</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                {{-- pt-3 bridges the gap below the link, so moving the mouse down doesn't close the dropdown. --}}
                <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute left-1/2 top-full z-50 -translate-x-1/2 pt-3">
                    <div class="min-w-52 rounded-lg bg-sb-header-menu-bg py-2 shadow-lg ring-1 ring-gray-900/5">
                        @foreach ($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                               @if ($menus->isCurrent($child['url'])) aria-current="page" @endif
                               @class(['block whitespace-nowrap px-4 py-2 text-sm font-medium', $menuLinkColors, '!text-sb-header-menu-link-hover' => $menus->isCurrent($child['url'])])>{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
@endforeach
