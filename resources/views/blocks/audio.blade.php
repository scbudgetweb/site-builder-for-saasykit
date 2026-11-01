{{--
    A sound file in a card: the cover image (or a note), the title, artist and description, then the browser's own player.
    preload="none": nothing is downloaded until it's played. Without "Offer a download link", the player's menu leaves
    Download out too (where the browser supports controlslist).
--}}
@include('site-builder::blocks.partials.heading')

@if ($audioUrl)
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
