{{--
    Branding › Custom CSS › What your CSS can use: the names that stay the same from one version of the package to the
    next (block types, item parts, brand colour variables), from ManageBranding::cssReference().
--}}
@php
    $code = 'rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-950 dark:bg-white/10 dark:text-white';
    $heading = 'text-sm font-semibold text-gray-950 dark:text-white';
    $muted = 'text-sm text-gray-500 dark:text-gray-400';
    $row = 'flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2';
@endphp

<div class="space-y-6">
    <p class="{{ $muted }}">{{ __('These names stay the same when the site builder is updated, so CSS written with them keeps working. Avoid layout classes such as .grid or .max-w-4xl: they can change in any update. Colours added or renamed above show here once saved.') }}</p>

    <section class="space-y-1">
        <h3 class="{{ $heading }}">{{ __('Brand colours') }}</h3>
        <p class="{{ $muted }}">{{ __('Use them as var(--sb-colour-…), so your CSS follows Branding when a colour changes.') }}</p>
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($colours as $variable => $colour)
                <li class="{{ $row }}">
                    <span class="flex items-center gap-2 text-sm text-gray-950 dark:text-white">
                        @if ($swatches[$variable] ?? null)
                            <span class="inline-block h-4 w-4 shrink-0 rounded border border-gray-300 dark:border-white/20" style="background: {{ $swatches[$variable] }}"></span>
                        @else
                            <span class="inline-block h-4 w-4 shrink-0 rounded border border-dashed border-gray-400" title="{{ __('The theme\'s standard colour') }}"></span>
                        @endif
                        {{ $colour['name'] }}
                    </span>
                    <code class="{{ $code }}">var({{ $variable }})</code>
                </li>
            @endforeach
            <li class="{{ $row }}">
                <span class="text-sm text-gray-950 dark:text-white">{{ __('Shades of the site colour, light to dark') }}</span>
                <code class="{{ $code }}">var(--color-primary-50) … var(--color-primary-950)</code>
            </li>
            <li class="{{ $row }}">
                <span class="text-sm text-gray-950 dark:text-white">{{ __('The site\'s fonts') }}</span>
                <code class="{{ $code }}">var(--font-sans), var(--font-sb-heading)</code>
            </li>
        </ul>
    </section>

    <section class="space-y-1">
        <h3 class="{{ $heading }}">{{ __('Parts of blocks with repeated items') }}</h3>
        <p class="{{ $muted }}">{{ __('Cards, numbers, steps, people, quotes, questions, images and logos. Most can also be styled without CSS, under the block\'s Design tab.') }}</p>
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($parts as $class => $description)
                <li class="{{ $row }}">
                    <span class="text-sm text-gray-950 dark:text-white">{{ $description }}</span>
                    <code class="{{ $code }}">{{ $class }}</code>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="space-y-1">
        <h3 class="{{ $heading }}">{{ __('One kind of block, or one block') }}</h3>
        <p class="{{ $muted }}">{{ __('Every block of a kind, e.g. [data-block="stats"] .sb-item-value { … }. For one block, give it a class in its Advanced tab (CSS ID & classes) and use that, e.g. .promo { … }.') }}</p>
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($blocks as $name => $label)
                <li class="{{ $row }}">
                    <span class="text-sm text-gray-950 dark:text-white">{{ $label }}</span>
                    <code class="{{ $code }}">[data-block="{{ $name }}"]</code>
                </li>
            @endforeach
        </ul>
    </section>
</div>
