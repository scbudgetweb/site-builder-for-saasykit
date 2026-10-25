{{--
    Site builder theme: shadows SaaSykit's components/layouts/focus.blade.php (login, register, password reset…).
    Changes: the side panel uses the Branding inner banner instead of SaaSykit's purple gradient, and the logo is the
    Branding "Sign-in screens & emails" logo (or the site name).
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.layouts.partials.head')
</head>
<body class="text-gray-900 bg-white">
    <div id="app">
        <div class="w-full">
            <div class="flex flex-col-reverse flex-wrap md:flex-nowrap md:flex-row">
                <div class="md:basis-3/5 flex flex-col">
                    <div class="hidden md:block m-6">
                        <a href="{{ route('home') }}" class="inline-flex"><x-site-builder::logo place="auth" /></a>
                    </div>

                    {{ $left }}
                </div>

                <div class="relative md:basis-2/5 md:min-h-screen flex flex-col text-center md:text-left md:text-sb-banner-heading"
                     style="--color-sb-banner-heading: var(--color-sb-inner-heading); --color-sb-banner-subtitle: var(--color-sb-inner-subtitle);">
                    <x-site-builder::banner-background variant="inner" class="hidden md:block" />

                    <div class="relative flex justify-between md:justify-end">
                        <div class="md:hidden m-6">
                            <a href="{{ route('home') }}" class="inline-flex"><x-site-builder::logo place="auth" /></a>
                        </div>

                        <div class="self-end m-4 text-xs">
                            <a href="{{ route('home') }}" class="underline-offset-4 hover:underline md:text-sb-banner-subtitle">{{ __('Back to the home page') }}</a>
                        </div>
                    </div>

                    <div class="relative">
                        {{ $right }}
                    </div>
                </div>
            </div>
        </div>

        @include('components.layouts.partials.tail')
    </div>
</body>
</html>
