{{--
    An image with a heading, text and buttons. $imagePosition: left / right (side by side from tablet width, stacked on
    phones) or above / below (always stacked). Without an image it's just the text.
    $buttonPosition: below or above the text, or below or above the image.
--}}
@php($isSideBySide = $imageUrl && in_array($imagePosition, ['left', 'right'], true))

<div @class([
    'grid items-center gap-8 md:grid-cols-2 lg:gap-16' => $isSideBySide,
    'flex flex-col gap-8' => $imageUrl && ! $isSideBySide,
])>
    @if ($imageUrl)
        <div @class(['md:order-2' => $imagePosition === 'right', 'order-2' => $imagePosition === 'below'])>
            @if ($buttons !== [] && $buttonPosition === 'above_image')
                <div class="mb-6">
                    @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => 'left'])
                </div>
            @endif

            <img src="{{ $imageUrl }}" alt="{{ $block['alt'] ?? '' }}" loading="lazy" decoding="async"
                 class="h-auto w-full rounded-sb-image" @if ($imageStyle !== '') style="{{ $imageStyle }}" @endif>

            @if ($buttons !== [] && $buttonPosition === 'below_image')
                <div class="mt-6">
                    @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => 'left'])
                </div>
            @endif
        </div>
    @endif

    <div>
        @if ($buttons !== [] && $buttonPosition === 'before_text')
            <div class="mb-6">
                @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => 'left'])
            </div>
        @endif

        @if (filled($block['heading'] ?? null))
            <h2 class="sb-h2 section-heading">{{ $block['heading'] }}</h2>
        @endif

        @if ($html !== '')
            <div @class(['block-text sb-prose', 'mt-4' => filled($block['heading'] ?? null)])>{!! $html !!}</div>
        @endif

        @if ($buttons !== [] && $buttonPosition === 'after_text')
            <div class="sb-block-buttons mt-8">
                @include('site-builder::blocks.partials.buttons', ['buttons' => $buttons, 'align' => 'left'])
            </div>
        @endif
    </div>
</div>
