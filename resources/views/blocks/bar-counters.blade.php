{{-- Percentages as bars, each with its label and number above it; each bar fills from 0 as it comes into view ($animate). --}}
@include('site-builder::blocks.partials.heading')
@php($meterColour ??= $isDark ? 'var(--color-primary-400)' : 'var(--color-primary-600)')

<dl class="sb-items grid gap-6">
    @foreach ($items as $item)
        <div class="sb-item" x-data="{{ \ScBudgetWeb\SiteBuilder\Blocks\Core\PercentCountersBlock::alpine($item['value'], $animate) }}"
             @if ($animate) x-init="shown = 0" x-intersect.once="fill()" @endif>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="sb-item-label font-semibold">{{ $item['label'] }}</dt>
                <dd class="sb-item-value font-bold tabular-nums"><span x-text="shown">{{ $item['value'] }}</span>{{ $percentSign }}</dd>
            </div>
            <div class="mt-2 overflow-hidden rounded-full" style="height: {{ $height }}; background: {{ $trackColour }}" aria-hidden="true">
                <div class="h-full rounded-full" style="width: {{ $item['value'] }}%; background: {{ $item['colour'] ?? $meterColour }}" :style="{ width: shown + '%' }"></div>
            </div>
        </div>
    @endforeach
</dl>
