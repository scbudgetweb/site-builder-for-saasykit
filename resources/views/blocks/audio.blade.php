{{--
    SoundCloud's or Spotify's own player ($embed), click-to-play until marketing cookies are allowed. Or a sound file in
    a card: the cover image (or a note), the title, artist and description, then the browser's own player.
    preload="none": nothing is downloaded until it's played. Without "Offer a download link", the player's menu leaves
    Download out too (where the browser supports controlslist).
--}}
@include('site-builder::blocks.partials.heading')

@if ($embed && $showsPlayerStraightAway)
    {{-- SoundCloud's or Spotify's own player, at its own height. --}}
    <iframe src="{{ $embed['url'] }}" title="{{ $title }}" height="{{ $embed['height'] }}" class="block w-full overflow-hidden rounded-sb-card border-0"
            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
@elseif ($embed)
    {{-- Click-to-play: nothing loads from SoundCloud or Spotify until the visitor presses play (GDPR). --}}
    <div x-data="{ playing: false }" class="relative w-full overflow-hidden rounded-sb-card bg-primary-900" style="height: {{ $embed['height'] }}px">
        <button type="button" x-show="! playing" @click="playing = true"
                class="group absolute inset-0 flex h-full w-full items-center gap-4 px-5 text-left text-white focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-white"
                aria-label="{{ __('Play :title from :provider', ['title' => $title, 'provider' => $embed['provider']]) }}">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/95 text-primary-600 shadow-lg transition-transform group-hover:scale-110" aria-hidden="true">
                <svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </span>
            <span class="min-w-0">
                <span class="block truncate font-semibold">{{ $title }}</span>
                <span class="mt-0.5 block text-xs opacity-80">{{ __('Plays from :provider, which may set cookies.', ['provider' => $embed['provider']]) }}</span>
            </span>
        </button>
        <template x-if="playing">
            <iframe src="{{ $embed['playUrl'] }}" title="{{ $title }}" class="absolute inset-0 h-full w-full border-0"
                    allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
        </template>
    </div>
@elseif ($audioUrl)
    <div class="sb-items grid">
        <figure class="sb-item m-0 rounded-sb-card border border-current/15 p-4 sm:p-5">
            <div class="flex items-center gap-4">
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-sb-image object-cover sm:h-20 sm:w-20">
                @else
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-sb-image bg-primary-600/10 text-primary-600 sm:h-20 sm:w-20" aria-hidden="true">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z"/></svg>
                    </span>
                @endif
                <figcaption class="min-w-0 flex-1">
                    <span class="sb-item-title block font-semibold">{{ $title }}</span>
                    @if ($artist !== '')
                        <span class="sb-item-meta mt-0.5 block text-sm opacity-70">{{ $artist }}</span>
                    @endif
                    @if ($description !== '')
                        <span class="sb-item-text block-text mt-1 block text-sm opacity-85">{{ $description }}</span>
                    @endif
                </figcaption>
            </div>
            <audio controls preload="none" src="{{ $audioUrl }}" class="mt-4 block w-full"
                   aria-label="{{ $artist !== '' ? __(':title, by :artist', ['title' => $title, 'artist' => $artist]) : $title }}"
                   @unless ($offersDownload) controlslist="nodownload" @endunless>
                <a href="{{ $audioUrl }}">{{ __('Listen to :title', ['title' => $title]) }}</a>
            </audio>
            @if ($offersDownload)
                <a href="{{ $audioUrl }}" download class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-inherit underline-offset-4 hover:underline">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    {{ __('Download') }}
                    <span class="sr-only">{{ $title }}</span>
                </a>
            @endif
        </figure>
    </div>
@endif
