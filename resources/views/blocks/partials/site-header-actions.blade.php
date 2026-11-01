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

@include('site-builder::theme.partials.menu-button', ['menuId' => $menuId, 'class' => $narrow.' text-sb-header-link'])
