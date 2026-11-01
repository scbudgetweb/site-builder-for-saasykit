{{--
    A new header or footer template's starting layout (Templates\TemplateLayouts), as cards with a small picture of
    each: radio buttons underneath, so it works from the keyboard. $layouts: key => {label, description}; $type: header
    or footer. Inline styles, as the admin theme doesn't build the package's own classes.
--}}
@php
    $statePath = $getStatePath();
    // Each picture: grey bars for text and links, a darker one for a logo or a button, on a 120 × 60 box.
    $bar = fn (int $x, int $y, int $w, int $h = 4, string $fill = 'currentColor', float $opacity = 0.35): string => "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"2\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
    $logo = fn (int $x, int $y): string => $bar($x, $y, 18, 6, 'currentColor', 0.8);
    $button = fn (int $x, int $y): string => $bar($x, $y, 14, 7, '#f59e0b', 1);
    $links = fn (int $x, int $y, int $count = 3): string => implode('', array_map(fn (int $i): string => $bar($x + $i * 12, $y + 1, 9), range(0, $count - 1)));
    $page = $type === 'header' ? $bar(10, 34, 100, 4, 'currentColor', 0.12).$bar(10, 42, 80, 4, 'currentColor', 0.12).$bar(10, 50, 90, 4, 'currentColor', 0.12) : '';
    $footerBg = fn (int $y, int $h): string => "<rect x=\"0\" y=\"{$y}\" width=\"120\" height=\"{$h}\" fill=\"#1f2937\"/>";
    $light = fn (string $svg): string => str_replace('currentColor', '#ffffff', $svg);
    $pictures = [
        'header' => [
            'standard' => $logo(8, 9).$links(56, 9).$button(98, 8).$page,
            'links_centre' => $logo(8, 9).$links(42, 9).$button(98, 8).$page,
            'centred_logo' => $logo(51, 5).$links(42, 16).$page,
            'split' => $links(10, 9, 2).$logo(51, 9).$links(78, 9, 2).$page,
            'menu_only' => $logo(8, 9).$bar(100, 8, 12, 2, 'currentColor', 0.8).$bar(100, 11, 12, 2, 'currentColor', 0.8).$bar(100, 14, 12, 2, 'currentColor', 0.8).$page,
            'top_bar' => '<rect x="0" y="0" width="120" height="8" fill="#312e81"/>'.$light($bar(6, 2, 30, 3, 'currentColor', 0.7).$bar(96, 2, 18, 3, 'currentColor', 0.7)).$logo(8, 14).$links(56, 14).$button(98, 13).$bar(10, 38, 100, 4, 'currentColor', 0.12).$bar(10, 46, 80, 4, 'currentColor', 0.12),
        ],
        'footer' => [
            'standard' => $footerBg(10, 50).$light($logo(8, 16).$bar(8, 26, 28).$bar(8, 34, 20).$links(56, 16, 1).$links(80, 16, 1).$links(100, 16, 1).$bar(56, 24, 9).$bar(80, 24, 9).$bar(100, 24, 9).$bar(8, 50, 40, 3)),
            'simple' => $footerBg(40, 20).$light($bar(8, 48, 40, 3).$bar(88, 47, 24, 5, 'currentColor', 0.6)),
            'columns' => $footerBg(10, 50).$light($logo(8, 16).$bar(8, 26, 28).$links(56, 16, 1).$links(80, 16, 1).$links(100, 16, 1).$bar(56, 24, 9).$bar(80, 24, 9).$bar(100, 24, 9).$bar(8, 50, 40, 3).$bar(88, 49, 24, 5, 'currentColor', 0.6)),
            'centred' => $footerBg(10, 50).$light($logo(51, 16).$bar(40, 27, 40).$bar(48, 36, 24, 5, 'currentColor', 0.6).$bar(40, 48, 40, 3)),
            'cta' => '<rect x="0" y="0" width="120" height="26" fill="#4f46e5"/>'.$light($bar(35, 6, 50, 5, 'currentColor', 0.9)).$button(53, 15).$footerBg(26, 34).$light($logo(8, 31).$links(56, 31, 1).$links(80, 31, 1).$links(100, 31, 1).$bar(8, 51, 40, 3)),
        ],
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ state: $wire.$entangle(@js($statePath)) }"
         role="radiogroup" aria-label="{{ $getLabel() }}"
         style="display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr));">
        @foreach ($layouts as $key => $layout)
            <label wire:key="layout-{{ $type }}-{{ $key }}"
                   x-bind:style="state === @js($key) ? 'border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500);' : ''"
                   style="display: flex; flex-direction: column; gap: 0.5rem; cursor: pointer; border: 1px solid rgb(128 128 128 / 0.3); border-radius: 0.75rem; padding: 0.75rem;">
                <svg viewBox="0 0 120 60" aria-hidden="true" style="width: 100%; height: auto; border-radius: 0.375rem; background: rgb(128 128 128 / 0.08);">
                    @if ($key === \ScBudgetWeb\SiteBuilder\Templates\TemplateLayouts::BLANK)
                        <rect x="8" y="8" width="104" height="44" rx="4" fill="none" stroke="currentColor" stroke-dasharray="4 3" opacity="0.4"/>
                    @else
                        {!! $pictures[$type][$key] ?? '' !!}
                    @endif
                </svg>
                <span style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <input type="radio" name="{{ $statePath }}" value="{{ $key }}" x-model="state" class="fi-radio-input" style="flex: none; width: 1rem; height: 1rem; margin-top: 0.2rem;">
                    <span>
                        <span style="display: block; font-weight: 600; font-size: 0.875rem;">{{ $layout['label'] }}</span>
                        <span style="display: block; font-size: 0.8125rem; opacity: 0.7;">{{ $layout['description'] }}</span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
