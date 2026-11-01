{{--
    Links to the previous (older) and next (newer) posts ($links), side by side from tablet size, the next one on the
    right even when there's no previous one. Each is one link: its label, the post's title and an optional image.
--}}
<nav class="sb-items grid gap-4 sm:grid-cols-2" aria-label="{{ __('More posts') }}">
    @foreach (['previous', 'next'] as $direction)
        @isset ($links[$direction])
            @php($link = $links[$direction])
            <a href="{{ $link['url'] }}" rel="{{ $direction === 'previous' ? 'prev' : 'next' }}" @class([
                'sb-item group flex items-center gap-4 rounded-sb-card border border-current/15 p-4 text-inherit no-underline transition-colors hover:border-current/40',
                'sm:col-start-2 sm:flex-row-reverse sm:text-right' => $direction === 'next',
            ])>
                @if ($link['imageUrl'])
                    <img src="{{ $link['imageUrl'] }}" alt="" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-sb-image object-cover sm:h-20 sm:w-20">
                @endif
                <span class="min-w-0 flex-1">
                    <span class="sb-item-meta flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide opacity-70 {{ $direction === 'next' ? 'sm:justify-end' : '' }}">
                        @if ($direction === 'previous')
                            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        @endif
                        {{ $link['label'] }}
                        @if ($direction === 'next')
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        @endif
                    </span>
                    <span class="sb-item-title mt-1 block font-semibold group-hover:underline">{{ $link['title'] }}</span>
                </span>
            </a>
        @endisset
    @endforeach
</nav>
