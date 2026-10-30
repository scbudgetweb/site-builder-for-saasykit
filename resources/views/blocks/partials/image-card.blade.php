{{-- One image card (see image-cards.blade.php). --}}
@php
    $icon = $card['icon'] ? \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($card['icon'], $style['iconSize']) : null;
    $hasIcon = $icon !== null && $icon->toHtml() !== '';
@endphp

<article class="sb-item {{ $cardClass }}" @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif>
    @if ($card['imageUrl'])
        <img src="{{ $card['imageUrl'] }}" alt="{{ $card['alt'] }}" loading="lazy" decoding="async"
             @class(['w-full object-cover', $imageShape, 'rounded-sb-image' => ! $style['hasBox']])
             @if (($imageCss = trim(($style['hasBox'] ? '' : $imageStyle).' '.$imageBorderStyle)) !== '') style="{{ $imageCss }}" @endif>
    @endif

    <div class="{{ $bodyClass }}">
        @if ($hasIcon)
            <span class="{{ $style['iconWrap'] }}">{{ $icon }}</span>
        @endif

        <div @class(['flex min-w-0 flex-1 flex-col', 'items-center' => $style['isCentered']])>
            @if ($card['title'] !== '')
                <h3 class="sb-item-title text-lg font-semibold">{{ $card['title'] }}</h3>
            @endif

            @if ($card['text'] !== '')
                <p @class(['sb-item-text block-text text-sm leading-relaxed opacity-80', 'mt-2' => $card['title'] !== ''])>{{ $card['text'] }}</p>
            @endif

            @if ($card['buttons'] !== [])
                <div class="mt-auto pt-6">
                    @include('site-builder::blocks.partials.buttons', ['buttons' => $card['buttons'], 'isDark' => $cardIsDark ?? $isDark, 'align' => $style['isCentered'] ? 'center' : 'left'])
                </div>
            @endif
        </div>
    </div>
</article>
