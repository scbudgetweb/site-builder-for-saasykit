{{-- Site builder theme: shadows SaaSykit's components/layouts/simple.blade.php (neutral text colour, our header and footer). --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.layouts.partials.head')
</head>
<body class="text-gray-900 bg-white" x-data>
    <div id="app" class="flex flex-col min-h-screen">
        <x-layouts.app.header />

        <main id="main" class="grow">
            <div class="mx-auto my-6 md:my-10 max-w-4xl px-4">
                {{ $slot }}
            </div>
        </main>

        <x-layouts.app.footer />

        @include('components.layouts.partials.tail')
    </div>
</body>
</html>
