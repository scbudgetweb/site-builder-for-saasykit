{{--
    One block. A block with Appearance gets a section (background, text colour, spacing, width, panel, anchor); every
    class and style comes from BlockAppearance::resolve()'s whitelists. Block views receive $block (the saved data),
    $isDark, $headingAlign, $index, $page and their viewData() keys.

    The background is drawn on the section, or on an inner panel when the block uses one. The image and its overlay
    are clipped in their own layer (with the panel's corners), so the block itself never clips: dropdowns and
    overlapping content can hang over the next block (lesson 8).
--}}
@php
    $viewData = ['block' => $block, 'page' => $page, 'index' => $index, 'isDark' => $appearance['isDark'] ?? false, 'headingAlign' => $appearance['headingAlign'] ?? null, ...$extra];

    $backgroundLayers = ($appearance['image'] ?? null)
        ? '<div class="absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">'
            .'<img src="'.e($appearance['image']).'" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">'
            .($appearance['overlayStyle'] ? '<div class="absolute inset-0" style="'.e($appearance['overlayStyle']).'"></div>' : '')
            .'</div>'
        : '';
@endphp

@if ($appearance === null)
    @include($blockClass::view(), $viewData)
@else
    <section @if ($appearance['id']) id="{{ $appearance['id'] }}" @endif
             class="{{ trim($appearance['class'].' '.$appearance['headingDecorationClass']) }} scroll-mt-24"
             @if ($appearance['style'] !== '') style="{{ $appearance['style'] }}" @endif
             data-block="{{ $blockClass::name() }}"
             @if ($appearance['textScheme']) data-text-scheme="{{ $appearance['textScheme'] }}" @endif>
        @unless ($appearance['panel'])
            {!! $backgroundLayers !!}
        @endunless

        <div class="relative {{ $appearance['container'] }}">
            @if ($appearance['panel'])
                <div class="sb-panel {{ $appearance['panel']['class'] }}" @if ($appearance['panel']['style'] !== '') style="{{ $appearance['panel']['style'] }}" @endif>
                    {!! $backgroundLayers !!}
                    <div class="relative">
                        @include($blockClass::view(), $viewData)
                    </div>
                </div>
            @else
                @include($blockClass::view(), $viewData)
            @endif
        </div>
    </section>
@endif
