{{--
    A Google map from Maps' plain embed (no API key). With marketing cookies allowed (or the cookie banner off), the map
    shows straight away. Otherwise a placeholder: nothing loads from Google until the visitor presses Show map (GDPR).
    That map is inside an Alpine <template>, which the browser doesn't load. Under it, optionally, the address and a
    Get directions link.
--}}
@include('site-builder::blocks.partials.heading')

@if ($map)
    @php($title = __('Map of :address', ['address' => $address]))
    <div @class(['relative w-full overflow-hidden rounded-sb-image bg-primary-900 shadow-sb-card', $heightClasses])
         @if ($imageStyle !== '') style="{{ $imageStyle }}" @endif
         @unless ($showsMapStraightAway) x-data="{ showing: false }" @endunless>
        @if ($showsMapStraightAway)
            <iframe src="{{ $map['embedUrl'] }}" title="{{ $title }}" class="absolute inset-0 h-full w-full border-0"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        @else
            <div x-show="! showing" class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center text-white">
                {{-- A few faint streets, drawn here, so the placeholder reads as a map without loading one. --}}
                <svg class="absolute inset-0 h-full w-full text-white/10" preserveAspectRatio="xMidYMid slice" viewBox="0 0 400 240" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-width="10" d="M-10 170 C 80 150, 140 190, 220 140 S 340 60, 410 80"/>
                    <path stroke-width="5" d="M90 -10 L 130 250 M 260 -10 C 250 80, 300 150, 280 250 M -10 60 L 410 40"/>
                    <path stroke-width="2" d="M30 -10 L 60 250 M 180 -10 L 200 250 M 340 -10 L 330 250 M -10 110 L 410 120 M -10 210 L 410 200"/>
                </svg>
                <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-white/95 text-primary-600 shadow-lg" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                </span>
                <span class="relative max-w-md text-sm font-semibold drop-shadow">{{ $address }}</span>
                <span class="relative flex flex-wrap items-center justify-center gap-2">
                    <button type="button" @click="showing = true" class="rounded-sb-button bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-lg transition-transform hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('Show map') }}</button>
                    <a href="{{ $map['openUrl'] }}" target="_blank" rel="noopener" class="rounded-sb-button px-4 py-2 text-sm font-semibold text-white underline-offset-4 hover:underline">{{ __('Open in Google Maps') }}</a>
                </span>
                <span class="relative text-xs opacity-80 drop-shadow">{{ __('The map loads from Google Maps, which may set cookies.') }}</span>
            </div>

            <template x-if="showing">
                <iframe src="{{ $map['embedUrl'] }}" title="{{ $title }}" class="absolute inset-0 h-full w-full border-0"
                        referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </template>
        @endif
    </div>

    @if ($addressLines !== [] || $showsDirections)
        <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            @if ($addressLines !== [])
                <address class="block-text not-italic">
                    @foreach ($addressLines as $line)
                        {{ $line }}@unless ($loop->last)<br>@endunless
                    @endforeach
                </address>
            @endif
            @if ($showsDirections)
                <a href="{{ $map['directionsUrl'] }}" target="_blank" rel="noopener" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-inherit underline-offset-4 hover:underline">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    {{ __('Get directions') }}
                    <span class="sr-only">{{ __('(opens Google Maps)') }}</span>
                </a>
            @endif
        </div>
    @endif
@endif
