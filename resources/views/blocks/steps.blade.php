{{--
    Steps, each with its number (or an icon) in a marker. Design › Items styles them; a connector line can join the
    markers across each row (.sb-steps-line, items.css), so the list knows its columns on wide screens.
--}}
@php
    $steps = $block['items'] ?? [];
    $wideColumns = count($steps) === 4 ? 4 : (count($steps) >= 3 ? 3 : 2);
    [$connectorClass, $connectorStyle] = \ScBudgetWeb\SiteBuilder\Blocks\Core\StepsBlock::connector($block);
@endphp

@include('site-builder::blocks.partials.heading')

<ol @class([
    'sb-items grid gap-8 sm:grid-cols-2',
    'lg:grid-cols-3' => $wideColumns === 3,
    'lg:grid-cols-4' => $wideColumns === 4,
    "sb-steps-lg-{$wideColumns}",
    $connectorClass,
]) @if ($connectorStyle !== '') style="{{ $connectorStyle }}" @endif>
    @foreach ($steps as $step)
        <li @class(['sb-item text-center', 'sb-item-highlight' => (bool) ($step['highlight'] ?? false)])>
            @php($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($step['icon'] ?? null, 'h-6 w-6'))
            <div class="sb-step-head flex items-center justify-center">
                <span class="sb-item-icon sb-item-marker sb-has-tile flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-600 text-lg font-bold text-white">
                    @if ($icon->toHtml() !== '')
                        {{ $icon }}<span class="sr-only">{{ __('Step :number', ['number' => $loop->iteration]) }}</span>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
            </div>
            <h3 class="sb-item-title mt-4 text-lg font-semibold">{{ $step['title'] ?? '' }}</h3>
            @if (filled($step['text'] ?? null))
                <p class="sb-item-text block-text mt-2 text-sm leading-relaxed opacity-80">{{ $step['text'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
