{{--
    A podcast's episodes (PodcastBlock): optionally the podcast's cover, name and page link, then each episode with a
    play button, title, date and length, and description, as rows (a line between) or cards in columns. One <audio> per
    block plays them (preload="none", so nothing downloads until play is pressed); starting one stops any other podcast
    on the page. With Show more, the older episodes are in the page, hidden, and shown a batch at a time.
    Without JavaScript, each episode has a plain Listen link instead of the player.
--}}
@include('site-builder::blocks.partials.heading')

@php
    $lineClamp = [1 => 'line-clamp-1', 2 => 'line-clamp-2', 3 => 'line-clamp-3', 4 => 'line-clamp-4', 5 => 'line-clamp-5'][$lines];
    $total = count($episodes);
@endphp

@if ($podcast)
    <div class="mb-8 flex items-center gap-4">
        @if ($podcast['coverUrl'])
            <img src="{{ $podcast['coverUrl'] }}" alt="" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-sb-image object-cover sm:h-20 sm:w-20">
        @endif
        <div class="min-w-0">
            <p class="text-lg font-semibold">{{ $podcast['title'] }}</p>
            @if ($podcast['link'])
                <a href="{{ $podcast['link'] }}" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-1 text-sm font-medium text-inherit opacity-80 underline-offset-4 hover:underline hover:opacity-100">
                    {{ __('All episodes') }}
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    <span class="sr-only">{{ __('(opens in a new tab)') }}</span>
                </a>
            @endif
        </div>
    </div>
@endif

@if ($episodes !== [])
    <div
        x-data="{
            current: null,
            playing: false,
            time: 0,
            duration: 0,
            shown: {{ $count }},
            init() {
                const audio = this.$refs.audio
                audio.addEventListener('timeupdate', () => this.time = audio.currentTime)
                audio.addEventListener('loadedmetadata', () => this.duration = Number.isFinite(audio.duration) ? audio.duration : this.duration)
                audio.addEventListener('play', () => { this.playing = true; this.$dispatch('sb-podcast-play', this.$el) })
                audio.addEventListener('pause', () => this.playing = false)
                audio.addEventListener('ended', () => { this.playing = false; this.time = 0 })
            },
            toggle(index, url, seconds) {
                const audio = this.$refs.audio

                if (this.current === index) {
                    audio.paused ? audio.play() : audio.pause()

                    return
                }

                this.current = index
                this.time = 0
                this.duration = seconds || 0
                audio.src = url
                audio.play()
            },
            seek(value) {
                this.$refs.audio.currentTime = value
                this.time = value
            },
            isPlaying(index) {
                return this.current === index && this.playing
            },
            clock(seconds) {
                const all = Math.max(0, Math.floor(seconds || 0))
                const hours = Math.floor(all / 3600)
                const minutes = Math.floor((all % 3600) / 60)
                const rest = String(all % 60).padStart(2, '0')

                return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`
            },
        }"
        x-on:sb-podcast-play.window="if ($event.detail !== $el) { $refs.audio.pause() }"
    >
        <audio x-ref="audio" preload="none" class="hidden"></audio>

        <ul role="list" @class([
            'sb-items grid',
            'gap-6' => $style['hasBox'] || $columns > 1,
            'sm:grid-cols-2' => $columns >= 2,
            'lg:grid-cols-3' => $columns === 3,
        ])>
            @foreach ($episodes as $index => $episode)
                <li
                    @class([
                        'sb-item flex items-start gap-4',
                        $cardClass => $style['hasBox'],
                        'border-b border-current/10 py-5' => ! $style['hasBox'],
                    ])
                    @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif
                    @if ($index >= $count) x-show="{{ $index }} < shown" style="display: none" @endif
                >
                    {{-- With artwork: the image on the left and the play button on the right (order-last); without it, the play button leads. --}}
                    @if ($episode['imageUrl'])
                        <img src="{{ $episode['imageUrl'] }}" alt="" loading="lazy" decoding="async" class="h-14 w-14 shrink-0 rounded-sb-image object-cover sm:h-20 sm:w-20">
                    @endif

                    <button type="button"
                            x-on:click="toggle({{ $index }}, @js($episode['audioUrl']), @js($episode['seconds']))"
                            x-bind:aria-pressed="isPlaying({{ $index }}) ? 'true' : 'false'"
                            x-bind:aria-label="(isPlaying({{ $index }}) ? @js(__('Pause: :title', ['title' => $episode['title']])) : @js(__('Play: :title', ['title' => $episode['title']])))"
                            aria-label="{{ __('Play: :title', ['title' => $episode['title']]) }}"
                            @class(['sb-item-icon sb-has-tile inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current', 'order-last self-center' => $withArtwork])>
                        <svg x-show="! isPlaying({{ $index }})" class="ml-0.5 h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg x-show="isPlaying({{ $index }})" x-cloak class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <h3 class="sb-item-title font-semibold leading-snug">
                            @if ($episode['link'])
                                <a href="{{ $episode['link'] }}" target="_blank" rel="noopener" class="text-inherit underline-offset-2 hover:underline">{{ $episode['title'] }}</a>
                            @else
                                {{ $episode['title'] }}
                            @endif
                        </h3>

                        @if ($episode['date'] !== '' || $episode['length'] !== '')
                            <p class="sb-item-meta mt-1 flex flex-wrap items-center gap-x-2 text-sm opacity-70">
                                @if ($episode['date'] !== '')
                                    <time datetime="{{ $episode['datetime'] }}">{{ $episode['date'] }}</time>
                                @endif
                                @if ($episode['date'] !== '' && $episode['length'] !== '')
                                    <span aria-hidden="true">·</span>
                                @endif
                                @if ($episode['length'] !== '')
                                    <span>{{ $episode['length'] }}</span>
                                @endif
                            </p>
                        @endif

                        @if ($episode['description'] !== '')
                            <p class="sb-item-text block-text mt-2 text-sm leading-relaxed opacity-80 {{ $lineClamp }}">{{ $episode['description'] }}</p>
                        @endif

                        {{-- The playing episode's progress: drag to move through it. --}}
                        <template x-if="current === {{ $index }}">
                            <div class="mt-3 flex items-center gap-3 text-xs tabular-nums opacity-80">
                                <span x-text="clock(time)">0:00</span>
                                <input type="range" min="0" step="1" x-bind:max="Math.max(1, Math.floor(duration))" x-bind:value="Math.floor(time)"
                                       x-on:input="seek($event.target.value)" aria-label="{{ __('Position in :title', ['title' => $episode['title']]) }}"
                                       class="h-1 min-w-0 flex-1 cursor-pointer accent-current">
                                <span x-text="clock(duration)"></span>
                            </div>
                        </template>

                        <noscript>
                            <a href="{{ $episode['audioUrl'] }}" class="mt-2 inline-block text-sm font-semibold text-inherit underline">{{ __('Listen') }}</a>
                        </noscript>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($total > $count)
            <div class="mt-8 text-center" x-show="shown < {{ $total }}" x-cloak>
                <button type="button" x-on:click="shown += {{ $count }}"
                        class="inline-flex items-center gap-2 rounded-sb-button border border-current/25 px-5 py-2.5 text-sm font-semibold text-inherit transition hover:border-current/50">
                    {{ __('Show more episodes') }}
                </button>
            </div>
        @endif
    </div>
@endif
