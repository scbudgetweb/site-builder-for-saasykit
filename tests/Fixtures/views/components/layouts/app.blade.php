{{-- A stand-in for the app's public layout (SaaSykit's needs its own routes and Vite). Same slots. --}}
<!doctype html>
<html>
<head>
    <title>{{ $title ?? '' }}</title>
    @isset($description)<meta name="description" content="{{ $description }}">@endisset
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    @isset($socialCard)<meta property="og:image" content="{{ $socialCard }}">@endisset
    @stack('head')
</head>
<body>
    {{ $slot }}
</body>
</html>
