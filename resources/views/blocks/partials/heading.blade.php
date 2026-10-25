{{--
    A block's optional heading and intro. $align: the block's own alignment, center (default) or left; the block's
    Layout › Heading alignment ($headingAlign) wins when set.
--}}
@php($align = in_array($headingAlign ?? null, ['left', 'center'], true) ? $headingAlign : ($align ?? 'center'))
@if (filled($block['heading'] ?? null) || filled($block['intro'] ?? null))
    <div @class(['mb-8 sm:mb-10 max-w-3xl', 'mx-auto text-center' => $align === 'center'])>
        @if (filled($block['heading'] ?? null))
            <h2 class="sb-h2 section-heading">{{ $block['heading'] }}</h2>
        @endif
        @if (filled($block['intro'] ?? null))
            <p class="block-text mt-3 text-base opacity-80 sm:text-lg">{{ $block['intro'] }}</p>
        @endif
    </div>
@endif
