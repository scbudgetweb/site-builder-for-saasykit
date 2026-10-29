{{-- Theme Builder › Account links: Log in and a button for visitors, the account menu once signed in. --}}
<div @class(['flex items-center gap-3', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
    @auth
        @if ($hasUserMenu)
            <x-dynamic-component component="layouts.app.user-menu" />
        @endif
    @else
        @if (\Illuminate\Support\Facades\Route::has('login'))
            <a href="{{ route('login') }}" class="font-medium text-sb-header-account-link transition-colors hover:text-sb-header-account-link-hover focus-visible:text-sb-header-account-link-hover">{{ $loginLabel }}</a>
        @endif
        @if ($showButton)
            <a href="{{ $buttonUrl }}" class="inline-flex rounded-sb-button bg-sb-header-button px-4 py-2 font-semibold text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover focus-visible:bg-sb-header-button-hover focus-visible:text-sb-header-button-text-hover">{{ $buttonLabel }}</a>
        @endif
    @endauth
</div>
