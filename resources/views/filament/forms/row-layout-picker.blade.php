{{--
    A new row's columns (VisualEditor's Add a row), as cards with a picture of each layout: radio buttons underneath, so
    it works from the keyboard. $layouts: key => label (SectionBlock::LAYOUTS). A layout's key gives its parts: "1-2"
    is one part and two parts, "3" three equal ones, "stacked" one. Inline styles, as the admin theme doesn't build the
    package's own classes.
--}}
@php
    $statePath = $getStatePath();
    $parts = fn (string $key): array => match (true) {
        $key === 'stacked' => [1],
        ctype_digit($key) => array_fill(0, (int) $key, 1),
        default => array_map('intval', explode('-', $key)),
    };
    // Each picture: the columns as boxes across a 120 × 40 box, 4 apart.
    $picture = function (string $key) use ($parts): string {
        $columns = $parts($key);
        $width = 120 - 4 * (count($columns) + 1);
        $x = 4;
        $svg = '';

        foreach ($columns as $part) {
            $w = $width * $part / array_sum($columns);
            $svg .= sprintf('<rect x="%.1f" y="4" width="%.1f" height="32" rx="3" fill="currentColor" opacity="0.3"/>', $x, $w);
            $x += $w + 4;
        }

        return $svg;
    };
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ state: $wire.$entangle(@js($statePath)) }"
         role="radiogroup" aria-label="{{ $getLabel() }}"
         style="display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));">
        @foreach ($layouts as $key => $label)
            <label wire:key="row-layout-{{ $key }}"
                   x-bind:style="state === @js((string) $key) ? 'border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500);' : ''"
                   style="display: flex; flex-direction: column; gap: 0.5rem; cursor: pointer; border: 1px solid rgb(128 128 128 / 0.3); border-radius: 0.75rem; padding: 0.625rem;">
                <svg viewBox="0 0 120 40" aria-hidden="true" style="width: 100%; height: auto; border-radius: 0.375rem; background: rgb(128 128 128 / 0.08);">
                    {!! $picture((string) $key) !!}
                </svg>
                <span style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <input type="radio" name="{{ $statePath }}" value="{{ $key }}" x-model="state" class="fi-radio-input" style="flex: none; width: 1rem; height: 1rem; margin-top: 0.15rem;">
                    <span style="font-size: 0.8125rem; line-height: 1.3;">{{ $label }}</span>
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
