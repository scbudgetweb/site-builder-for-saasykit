{{-- One icon on its own, styled by Design › Icon (.sb-item-icon); with a $label it's read out, without it it's decoration. --}}
@php($svg = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($icon, 'shrink-0'))
@if ($svg->toHtml() !== '')
    <div @class(['sb-icon-block flex', 'justify-start' => $align === 'left', 'justify-center' => $align === 'center', 'justify-end' => $align === 'right'])>
        <span class="sb-item inline-flex">
            <span class="sb-item-icon inline-flex text-primary-600 transition-colors"
                  @if ($label !== '') role="img" aria-label="{{ $label }}" @endif>{{ $svg }}</span>
        </span>
    </div>
@endif
