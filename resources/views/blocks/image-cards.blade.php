{{--
    Image cards: an image (or icon), title, text and button, every part optional. Cards in a row are the
    same height with their buttons along the bottom. With more cards than columns (and the carousel on)
    they become a scroll-snap carousel with arrows and swipe, which can move on by itself (not for
    visitors who prefer reduced motion). Classes come from ImageCardsBlock::viewData() and CardStyle.
--}}
@php
    $renderCard = function (array $card) use ($style, $cardClass, $bodyClass, $cardIsDark, $isDark, $imageShape, $imageStyle, $imageBorderStyle): string {
        return view('site-builder::blocks.partials.image-card', compact('card', 'style', 'cardClass', 'bodyClass', 'cardIsDark', 'isDark', 'imageShape', 'imageStyle', 'imageBorderStyle'))->render();
    };
@endphp

@include('site-builder::blocks.partials.heading')

@if ($isCarousel)
    <div x-data="{
            paused: false,
            timer: null,
            step(direction) {
                const strip = this.$refs.strip;
                const slide = (strip.firstElementChild?.offsetWidth ?? strip.clientWidth) + 24;
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
                    this.timer = setInterval(() => { if (! this.paused) this.step(1) }, 5000);
                }
            },
            destroy() { clearInterval(this.timer) },
         }"
         @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
        <ul x-ref="strip" class="no-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto pb-2" aria-label="{{ __('Cards') }}">
            @foreach ($cards as $card)
                <li class="{{ $carouselWidth }} shrink-0 snap-start">{!! $renderCard($card) !!}</li>
            @endforeach
        </ul>

        <div class="mt-6 flex justify-center gap-3">
            <button type="button" @click="step(-1)" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-current/20 transition hover:bg-current/10" aria-label="{{ __('Previous cards') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="step(1)" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-current/20 transition hover:bg-current/10" aria-label="{{ __('Next cards') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
@else
    <div @class([
        'grid gap-6 sm:grid-cols-2',
        'lg:grid-cols-3' => $columns === 3,
        'lg:grid-cols-4' => $columns === 4,
    ])>
        @foreach ($cards as $card)
            {!! $renderCard($card) !!}
        @endforeach
    </div>
@endif
