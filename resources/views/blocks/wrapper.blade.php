{{--
    One block. A block with Appearance gets a section (background, text colour, spacing, width, panel, anchor); every
    class and style comes from BlockAppearance::resolve()'s whitelists. Block views receive $block (the saved data),
    $isDark, $headingAlign, $index, $page and their viewData() keys.

    The background is drawn on the section, or on an inner panel when the block uses one. The image and its overlay
    are clipped in their own layer (with the panel's corners), so the block itself never clips: dropdowns and
    overlapping content can hang over the next block (lesson 8).

    Content › Link makes the whole block clickable with a link laid over it (.sb-block-link), under the block's own
    buttons and links.

    Its design options ($design, BlockDesign) add classes and CSS properties to the section, the box (the panel, or the
    section without one) and the container.

    On the visual builder's canvas ($marksNodes), the block is wrapped in a display: contents element carrying its uid,
    so the editor can find it without changing the layout.
--}}
@php
    $marksNodes ??= false;
    /** @var \ScBudgetWeb\SiteBuilder\Blocks\Design\DesignOutput|null $design */
    $design ??= null;
    $hasPanel = (bool) ($appearance['panel'] ?? false);
    $join = fn (?string ...$parts): string => implode(' ', array_filter($parts, fn (?string $part): bool => $part !== null && trim($part) !== ''));
    $sectionClass = $join($appearance['class'] ?? null, $appearance['headingDecorationClass'] ?? null, $design?->classes('section'), $hasPanel ? null : $design?->classes('box'));
    $sectionStyle = $join($appearance['style'] ?? null, $design?->style('section'), $hasPanel ? null : $design?->style('box'));
    $panelClass = $join($appearance['panel']['class'] ?? null, $hasPanel ? $design?->classes('box') : null);
    $panelStyle = $join($appearance['panel']['style'] ?? null, $hasPanel ? $design?->style('box') : null);
    $containerClass = $join($appearance['container'] ?? null, $design?->classes('container'));
    $containerStyle = (string) $design?->style('container');
    $viewData = ['block' => $block, 'page' => $page, 'index' => $index, 'isDark' => $appearance['isDark'] ?? false, 'headingAlign' => $appearance['headingAlign'] ?? null, 'marksNodes' => $marksNodes, ...$extra];

    $backgroundLayers = ($appearance['image'] ?? null)
        ? '<div class="absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">'
            .'<img src="'.e($appearance['image']).'" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">'
            .($appearance['overlayStyle'] ? '<div class="absolute inset-0" style="'.e($appearance['overlayStyle']).'"></div>' : '')
            .'</div>'
        : '';
@endphp

@if ($marksNodes)
    <div data-sb-node="block" data-sb-uid="{{ $uid }}" data-sb-type="{{ $blockClass::name() }}" style="display: contents">
@endif
@if ($appearance === null)
    @include($blockClass::view(), $viewData)
@else
    <section @if ($appearance['id']) id="{{ $appearance['id'] }}" @endif
             class="{{ $join($sectionClass, 'scroll-mt-24', ($appearance['link'] ?? null) ? 'sb-has-link' : null) }}"
             @if ($sectionStyle !== '') style="{{ $sectionStyle }}" @endif
             data-block="{{ $blockClass::name() }}"
             @if ($appearance['textScheme']) data-text-scheme="{{ $appearance['textScheme'] }}" @endif>
        @unless ($appearance['panel'])
            {!! $backgroundLayers !!}
        @endunless

        {{-- Content › Link: the whole block is clickable; its own buttons and links sit above this. --}}
        @if ($appearance['link'] ?? null)
            <a href="{{ $appearance['link']['url'] }}" class="sb-block-link"
               aria-label="{{ is_string($block['heading'] ?? null) && filled($block['heading']) ? $block['heading'] : __('Open link') }}"
               @if ($appearance['link']['newTab']) target="_blank" rel="noopener" @endif></a>
        @endif

        <div class="relative {{ $containerClass }}" @if ($containerStyle !== '') style="{{ $containerStyle }}" @endif>
            @if ($appearance['panel'])
                <div class="sb-panel {{ $panelClass }}" @if ($panelStyle !== '') style="{{ $panelStyle }}" @endif>
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
@if (! $marksNodes && str_contains($sectionClass, 'sb-d-animate'))
    @once
        @include('site-builder::blocks.partials.motion-script')
    @endonce
@endif
@if ($marksNodes)
    </div>
@endif
