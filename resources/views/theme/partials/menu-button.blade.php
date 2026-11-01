{{--
    The menu button that opens the mobile menu, in the look chosen in Website › Navigation › Mobile menu: three lines,
    two lines or dots; plain, in a circle or in a rounded square; with or without "Menu" beside it. Shared by the
    standard header, the Menu block and the Header bar. $menuId: the menu it opens; $class: when it shows (and colour).
--}}
@php
    $mobileMenu ??= app(\ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus::class)->mobile();
    $buttonIcon = $mobileMenu['button_icon'] ?? 'lines';
    $buttonShape = $mobileMenu['button_shape'] ?? 'plain';
    $hasLabel = (bool) ($mobileMenu['button_label'] ?? false);
@endphp
<button type="button" @click="open = true" :aria-expanded="open.toString()" aria-controls="{{ $menuId }}" aria-expanded="false"
        @class([
            $class ?? 'inline-flex',
            'sb-menu-button shrink-0 items-center justify-center gap-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current',
            'h-10' => $buttonShape !== 'circle',
            'h-11' => $buttonShape === 'circle',
            'w-10' => ! $hasLabel && $buttonShape !== 'circle',
            'w-11' => ! $hasLabel && $buttonShape === 'circle',
            'px-3' => $hasLabel,
            'rounded-md hover:bg-current/10' => $buttonShape === 'plain',
            'rounded-full bg-current/10 hover:bg-current/20' => $buttonShape === 'circle',
            'rounded-sb-button bg-current/10 hover:bg-current/20' => $buttonShape === 'square',
        ])>
    @if ($hasLabel)
        <span class="text-sm font-semibold">{{ __('Menu') }}</span>
    @else
        <span class="sr-only">{{ __('Menu') }}</span>
    @endif
    @if ($buttonIcon === 'dots')
        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    @elseif ($buttonIcon === 'two_lines')
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 9h16M4 15h16" /></svg>
    @else
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
    @endif
</button>
