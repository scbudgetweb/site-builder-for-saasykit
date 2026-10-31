{{--
    Blog posts as cards (PostCardsBlock): image, date and category, title and description. The title's link covers
    the whole card, so the card is one link for screen readers and one click target for everyone else.
--}}
@include('site-builder::blocks.partials.heading')

@if ($cards !== [])
    <div @class([
        'sb-items grid gap-6 sm:grid-cols-2',
        'lg:grid-cols-3' => $columns === 3,
        'lg:grid-cols-4' => $columns === 4,
    ])>
        @foreach ($cards as $card)
            <article class="sb-item group {{ $cardClass }}" @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif>
                @if ($card['imageUrl'])
                    <img src="{{ $card['imageUrl'] }}" alt="" loading="lazy" decoding="async"
                         @class(['w-full object-cover', $imageShape, 'rounded-sb-image' => ! $style['hasBox']])
                         @if (($imageCss = trim(($style['hasBox'] ? '' : $imageStyle).' '.$imageBorderStyle)) !== '') style="{{ $imageCss }}" @endif>
                @endif

                <div class="{{ $bodyClass }}">
                    @if ($card['date'] !== '' || $card['category'] !== '')
                        <p class="sb-item-meta mb-2 flex flex-wrap items-center gap-x-2 text-xs font-medium uppercase tracking-wide opacity-70">
                            @if ($card['date'] !== '')
                                @if ($card['datetime'])
                                    <time datetime="{{ $card['datetime'] }}">{{ $card['date'] }}</time>
                                @else
                                    <span>{{ $card['date'] }}</span>
                                @endif
                            @endif
                            @if ($card['date'] !== '' && $card['category'] !== '')
                                <span aria-hidden="true">·</span>
                            @endif
                            @if ($card['category'] !== '')
                                <span>{{ $card['category'] }}</span>
                            @endif
                        </p>
                    @endif

                    <h3 class="sb-item-title text-lg font-semibold">
                        <a href="{{ $card['url'] }}" class="text-inherit underline-offset-2 after:absolute after:inset-0 group-hover:underline">{{ $card['title'] }}</a>
                    </h3>

                    @if ($card['excerpt'] !== '')
                        <p class="sb-item-text block-text mt-2 text-sm leading-relaxed opacity-80">{{ $card['excerpt'] }}</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if (! empty($pagination))
        <nav class="sb-pagination mt-10" aria-label="{{ __('Pages of posts') }}">{{ $pagination }}</nav>
    @endif
@endif
