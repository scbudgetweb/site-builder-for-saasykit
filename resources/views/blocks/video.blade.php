{{--
    With marketing cookies allowed (or the cookie banner off), the player shows straight away. Otherwise
    click-to-play: nothing loads from YouTube, Vimeo or Adilo until the visitor presses play (GDPR). That
    player is inside an Alpine <template>, which the browser doesn't load, and the poster is our own image.
--}}
@include('site-builder::blocks.partials.heading')

@if ($embed && $showsPlayerStraightAway)
    <div class="relative aspect-video w-full overflow-hidden rounded-sb-image bg-primary-900 shadow-sb-card">
        <iframe src="{{ $embed['playerUrl'] }}" title="{{ $block['title'] ?? '' }}" class="absolute inset-0 h-full w-full"
                allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
    </div>
@elseif ($embed)
    <div x-data="{ playing: false }" class="relative aspect-video w-full overflow-hidden rounded-sb-image bg-primary-900 shadow-sb-card">
        <button type="button" x-show="! playing" @click="playing = true"
                class="group absolute inset-0 flex h-full w-full flex-col items-center justify-center gap-3 text-white focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-white"
                aria-label="{{ __('Play video: :title', ['title' => $block['title'] ?? '']) }}">
            @if ($posterUrl)
                <img src="{{ $posterUrl }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                <span class="absolute inset-0 bg-black/30 transition-colors group-hover:bg-black/40" aria-hidden="true"></span>
            @endif
            <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-white/95 text-primary-600 shadow-lg transition-transform group-hover:scale-110" aria-hidden="true">
                <svg class="ml-1 h-9 w-9" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </span>
            <span class="relative max-w-md px-4 text-center text-sm font-semibold drop-shadow">{{ $block['title'] ?? '' }}</span>
            <span class="relative text-xs opacity-80 drop-shadow">{{ __('Plays from :provider, which may set cookies.', ['provider' => $embed['provider']]) }}</span>
        </button>

        <template x-if="playing">
            <iframe src="{{ $embed['embedUrl'] }}" title="{{ $block['title'] ?? '' }}" class="absolute inset-0 h-full w-full"
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
        </template>
    </div>
@endif
