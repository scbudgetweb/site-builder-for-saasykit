{{-- Percentages as rings: the number inside, the label under it; each ring fills from 0 as it comes into view ($animate). --}}
@include('site-builder::blocks.partials.heading')
@php($meterColour ??= $isDark ? 'var(--color-primary-400)' : 'var(--color-primary-600)')

<dl @class([
    'sb-items grid gap-8 text-center',
    'sm:grid-cols-2' => count($items) > 1,
    'lg:grid-cols-3' => count($items) === 3 || count($items) > 4,
    'lg:grid-cols-4' => count($items) === 4,
])>
    @foreach ($items as $item)
        {{-- The label (dt) comes first for screen readers, and shows under the ring. --}}
        <div class="sb-item flex flex-col-reverse items-center" x-data="{{ \ScBudgetWeb\SiteBuilder\Blocks\Core\PercentCountersBlock::alpine($item['value'], $animate) }}"
             @if ($animate) x-init="shown = 0" x-intersect.once="fill()" @endif>
            <dt class="sb-item-label mt-3 text-base font-semibold">{{ $item['label'] }}</dt>
            <dd class="relative" style="width: {{ $size }}; height: {{ $size }}">
                <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true" focusable="false">
                    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $stroke }}" style="stroke: {{ $trackColour }}"/>
                    <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $stroke }}" stroke-linecap="round" pathLength="100"
                            stroke-dasharray="100" stroke-dashoffset="{{ 100 - $item['value'] }}" :stroke-dashoffset="100 - shown"
                            style="stroke: {{ $item['colour'] ?? $meterColour }}"/>
                </svg>
                <span class="sb-item-value absolute inset-0 flex items-center justify-center text-2xl font-extrabold tabular-nums sm:text-3xl"><span x-text="shown">{{ $item['value'] }}</span>{{ $percentSign }}</span>
            </dd>
        </div>
    @endforeach
</dl>
