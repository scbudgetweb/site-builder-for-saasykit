@props(['backButton' => true])

{{--
    Site builder theme: shadows SaaSykit's components/layouts/focus-center.blade.php.
    Changes: neutral text colour and the Branding "Sign-in screens & emails" logo (or the site name).
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.layouts.partials.head')
</head>
<body {{ $attributes->merge(['class' => 'text-gray-900 bg-white w-full']) }}>
    <div id="app">
        <div class="flex justify-between">
            <a href="{{ route('home') }}" class="inline-flex m-6"><x-site-builder::logo place="auth" /></a>

            @if ($backButton)
                <div class="self-end m-4 text-xs">
                    <a href="{{ route('home') }}" class="text-gray-600 underline-offset-4 hover:underline">{{ __('Back to the home page') }}</a>
                </div>
            @endif
        </div>

        <div>
            {{ $slot }}
        </div>

        @include('components.layouts.partials.tail', ['skipCookieContentBar' => true])
    </div>
</body>
</html>
