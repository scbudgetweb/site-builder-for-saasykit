{{--
    Logos as a row (left, centred or right), an endless scrolling loop (the list twice, slid by half its width), or a
    carousel (a scroll-snap strip with arrows, swipe and optional autoplay). Moving logos stay still for
    visitors who prefer reduced motion, and otherwise have a pause button (WCAG 2.2.2). Sizes, speed and display come from LogoStripBlock::viewData().
--}}
@php
    $greyscale = (bool) ($block['greyscale'] ?? true);

    $logoHtml = function (array $logo) use ($greyscale, $imageClass): string {
        $image = '<img src="'.e($logo['url']).'" alt="'.e($logo['name']).'" loading="lazy" class="'.e($imageClass).' w-auto object-contain transition '
            .($greyscale ? 'grayscale opacity-70 hover:grayscale-0 hover:opacity-100' : '').'">';

        return filled($logo['link']) && preg_match('/^https?:\/\//i', $logo['link'])
            ? '<a href="'.e($logo['link']).'" target="_blank" rel="noopener" title="'.e($logo['name']).'">'.$image.'</a>'
            : $image;
    };
@endphp

@include('site-builder::blocks.partials.heading')

@if ($display === 'loop')
    @php
        // A short list is repeated so each half of the track is wider than the screen; only the first
        // pass is read out and focusable.
        $repeats = (int) ceil(10 / max(1, count($logos)));
    @endphp
    <div x-data="{ paused: false }">
        <div class="logo-loop overflow-hidden" :class="{ 'is-paused': paused }">
            <div class="logo-loop-track flex w-max" style="--logo-loop-duration: {{ max(10, count($logos) * $repeats * $secondsPerLogo) }}s;">
                @foreach ([false, true] as $isSecondHalf)
                    <ul class="flex shrink-0 items-center gap-x-16 pr-16" @if ($isSecondHalf) aria-hidden="true" @endif>
                        @for ($pass = 0; $pass < $repeats; $pass++)
                            @foreach ($logos as $logo)
                                <li class="shrink-0" @if ($isSecondHalf || $pass > 0) aria-hidden="true" inert @endif>{!! $logoHtml($logo) !!}</li>
                            @endforeach
                        @endfor
                    </ul>
                @endforeach
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" @click="paused = ! paused"
                    class="logo-strip-pause inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium opacity-70 transition hover:opacity-100 motion-reduce:hidden">
                <svg x-show="! paused" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                <svg x-show="paused" x-cloak class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>
                <span x-text="paused ? @js(__('Play logos')) : @js(__('Pause logos'))">{{ __('Pause logos') }}</span>
            </button>
        </div>
    </div>
@elseif ($display === 'carousel')
    <div x-data="{
            paused: false,
            stopped: false,
            timer: null,
            step(direction) {
                const strip = this.$refs.strip;
                const slide = strip.firstElementChild?.offsetWidth ?? strip.clientWidth;
                const atEnd = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 4;
                const atStart = strip.scrollLeft <= 4;

                if (direction > 0 && atEnd) {
                    strip.scrollTo({ left: 0, behavior: 'smooth' });
                } else if (direction < 0 && atStart) {
                    strip.scrollTo({ left: strip.scrollWidth, behavior: 'smooth' });
                } else {
                    strip.scrollBy({ left: direction * slide, behavior: 'smooth' });
                }
            },
            init() {
                if ({{ $autoplay ? 'true' : 'false' }} && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.timer = setInterval(() => { if (! this.paused && ! this.stopped) this.step(1) }, 3000);
                }
            },
            destroy() { clearInterval(this.timer) },
         }"
         @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
        <div class="flex items-center gap-2 sm:gap-4">
            <button type="button" @click="step(-1)" class="shrink-0 rounded-full p-2 opacity-70 transition hover:opacity-100" aria-label="{{ __('Previous logos') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <ul x-ref="strip" class="no-scrollbar flex min-w-0 flex-1 snap-x snap-mandatory overflow-x-auto">
                @foreach ($logos as $logo)
                    <li class="{{ $slideWidth }} flex shrink-0 snap-start items-center justify-center px-4">{!! $logoHtml($logo) !!}</li>
                @endforeach
            </ul>

            <button type="button" @click="step(1)" class="shrink-0 rounded-full p-2 opacity-70 transition hover:opacity-100" aria-label="{{ __('Next logos') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        @if ($autoplay)
            <div class="mt-4 flex justify-end">
                <button type="button" @click="stopped = ! stopped"
                        class="logo-strip-pause inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium opacity-70 transition hover:opacity-100 motion-reduce:hidden">
                    <svg x-show="! stopped" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                    <svg x-show="stopped" x-cloak class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>
                    <span x-text="stopped ? @js(__('Play logos')) : @js(__('Pause logos'))">{{ __('Pause logos') }}</span>
                </button>
            </div>
        @endif
    </div>
@else
    <ul class="flex flex-wrap items-center {{ $alignClass }} gap-x-10 gap-y-8">
        @foreach ($logos as $logo)
            <li class="sb-item">{!! $logoHtml($logo) !!}</li>
        @endforeach
    </ul>
@endif
