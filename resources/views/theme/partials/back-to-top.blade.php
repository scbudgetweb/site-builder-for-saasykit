{{--
    Branding › Header & footer › Back to top: a round button in a bottom corner ($place: right or left), in the header
    button's colours, once the page has scrolled most of a screen. It goes straight to the top for visitors who turn
    off animations (the page otherwise scrolls smoothly), then moves focus to the top of the page.
--}}
<button type="button"
        x-data="{ shown: false }"
        x-init="shown = window.scrollY > window.innerHeight * 0.75"
        x-on:scroll.window.passive="shown = window.scrollY > window.innerHeight * 0.75"
        x-show="shown"
        x-transition.opacity.duration.200ms
        x-cloak
        x-on:click="window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); document.getElementById('app')?.focus({ preventScroll: true })"
        @class([
            'sb-back-to-top fixed bottom-[max(1.25rem,env(safe-area-inset-bottom))] z-30 inline-flex h-11 w-11 items-center justify-center rounded-full shadow-lg',
            'bg-sb-header-button text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sb-header-button',
            'right-5' => $place !== 'left',
            'left-5' => $place === 'left',
        ])
        aria-label="{{ __('Back to top') }}" title="{{ __('Back to top') }}">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 12 8.25l7.5 7.5"/></svg>
</button>
