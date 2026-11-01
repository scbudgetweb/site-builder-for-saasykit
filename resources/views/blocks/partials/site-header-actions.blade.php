{{--
    Header bar: Log in (or the account menu once signed in) and the button, from the breakpoint up ($wide), then the menu
    button below it ($narrow). The button can stay beside the menu button on phones.
--}}
@if ($showsAccount)
    <div @class([$wide, 'items-center'])>
        @auth
            @if ($hasUserMenu)
                <x-dynamic-component component="layouts.app.user-menu" />
            @endif
        @else
            @if (\Illuminate\Support\Facades\Route::has('login'))
                <a href="{{ route('login') }}" class="font-medium text-sb-header-account-link transition-colors hover:text-sb-header-account-link-hover focus-visible:text-sb-header-account-link-hover">{{ $loginLabel }}</a>
            @endif
        @endauth
    </div>
@endif

@if ($cta !== null)
    <a href="{{ $cta['url'] }}" @if ($cta['newTab']) target="_blank" rel="noopener" @endif
       @class([$cta['onPhones'] || $isAlways ? 'inline-flex' : $wideInline, 'items-center whitespace-nowrap', $cta['classes']])>{{ $cta['label'] }}</a>
@endif

<button type="button" @class([$narrow, 'h-10 w-10 items-center justify-center rounded-md text-sb-header-link hover:bg-current/10 focus-visible:bg-current/10'])
        @click="open = true" :aria-expanded="open.toString()" aria-controls="{{ $menuId }}" aria-expanded="false">
    <span class="sr-only">{{ __('Menu') }}</span>
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
</button>
