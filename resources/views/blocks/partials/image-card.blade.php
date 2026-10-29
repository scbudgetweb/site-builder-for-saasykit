{{-- One image card (see image-cards.blade.php). --}}
@php
    $icon = $card['icon'] ? \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($card['icon'], $style['iconSize']) : null;
    $hasIcon = $icon !== null && $icon->toHtml() !== '';
@endphp

<article class="sb-item {{ $cardClass }}" @if ($style['cardStyle'] !== '') style="{{ $style['cardStyle'] }}" @endif @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif>
    @if ($card['imageUrl'])
        <img src="{{ $card['imageUrl'] }}" alt="{{ $card['alt'] }}" loading="lazy" decoding="async"
             @class(['w-full object-cover', $imageShape, 'rounded-sb-image' => ! $style['hasBox']])
             @if (($imageCss = trim(($style['hasBox'] ? '' : $imageStyle).' '.$imageBorderStyle)) !== '') style="{{ $imageCss }}" @endif>
    @endif

    <div class="{{ $bodyClass }}">
        @if ($hasIcon)
            <span class="{{ $style['iconWrap'] }}" @if ($style['iconStyle'] !== '') style="{{ $style['iconStyle'] }}" @endif>{{ $icon }}</span>
        @endif

        <div @class(['flex min-w-0 flex-1 flex-col', 'items-center' => $style['isCentered']])>
            @if ($card['title'] !== '')
                <h3 class="text-lg font-semibold" @if ($style['titleStyle'] !== '') style="{{ $style['titleStyle'] }}" @endif>{{ $card['title'] }}</h3>
            @endif

            @if ($card['text'] !== '')
                <p @class(['block-text text-sm leading-relaxed opacity-80', 'mt-2' => $card['title'] !== '']) @if ($style['textStyle'] !== '') style="{{ $style['textStyle'] }}" @endif>{{ $card['text'] }}</p>
            @endif

            @if ($card['buttons'] !== [])
                <div class="mt-auto pt-6">
                    @include('site-builder::blocks.partials.buttons', ['buttons' => $card['buttons'], 'isDark' => $cardIsDark ?? $isDark, 'align' => $style['isCentered'] ? 'center' : 'left'])
                </div>
            @endif
        </div>
    </div>
</article>
