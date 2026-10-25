{{--
    Block buttons. $buttons: the saved buttons, re-checked here (text and an allowed link, lesson 11);
    $isDark: the background behind them is dark; $align: left / center / right.
    Classes and colours come from PageBlock::buttonLook().
--}}
@php
    $align ??= 'center';
    $buttons = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::validButtons($buttons ?? []);
@endphp

@if (! empty($buttons))
    <div @class([
        'flex flex-wrap gap-3',
        'justify-center' => $align === 'center',
        'justify-end' => $align === 'right',
    ])>
        @foreach ($buttons as $button)
            @php($look = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::buttonLook($button, $isDark ?? false))
            <a href="{{ $button['url'] }}"
               @if (! empty($button['new_tab'])) target="_blank" rel="noopener" @endif
               class="{{ $look['class'] }}"
               @if ($look['style'] !== '') style="{{ $look['style'] }}" @endif>
                {{ \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render(is_string($button['icon'] ?? null) ? $button['icon'] : null, 'h-5 w-5 -ml-1') }}
                {{ $button['label'] }}
            </a>
        @endforeach
    </div>
@endif
