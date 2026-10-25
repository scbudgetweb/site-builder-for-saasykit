{{-- Site builder theme: shadows SaaSykit's components/layouts/app.blade.php (neutral text colour, our header and footer). --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.layouts.partials.head')
</head>
<body class="text-gray-900 bg-white flex flex-col min-h-screen" x-data>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:text-gray-900 focus:shadow">{{ __('Skip to content') }}</a>

    <div id="app" class="flex flex-col grow">
        <x-layouts.app.header class="shrink-0" />

        <main id="main" class="grow">
            {{ $slot }}
        </main>

        <x-layouts.app.footer class="shrink-0" />

        @include('components.layouts.partials.tail')
    </div>
    <x-impersonate::banner />
</body>
</html>
