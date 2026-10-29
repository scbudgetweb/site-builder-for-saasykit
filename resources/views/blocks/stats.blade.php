@php($animate = (bool) ($block['animate'] ?? true))

@include('site-builder::blocks.partials.heading')

<dl @class([
    'grid gap-8 text-center',
    'sm:grid-cols-2' => count($items) > 1,
    'lg:grid-cols-3' => count($items) === 3 || count($items) > 4,
    'lg:grid-cols-4' => count($items) === 4,
])>
    @foreach ($items as $item)
        @php($number = $item['number'] ?? 0)
        <div class="sb-item">
            @if (($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($item['icon'] ?? null, 'h-8 w-8'))->toHtml() !== '')
                <span class="mb-3 inline-flex text-primary-600" @if ($isDark) style="color: inherit" @endif>{{ $icon }}</span>
            @endif
            <dt class="sr-only">{{ $item['label'] ?? '' }}</dt>
            <dd class="text-4xl sm:text-5xl font-extrabold text-primary-600" @if ($isDark) style="color: inherit" @endif>
                {{ $item['prefix'] ?? '' }}<span
                    @if ($animate)
                        x-data="{ shown: 0, target: {{ (float) $number }} }"
                        x-intersect.once="if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { shown = target; return; } const start = performance.now(); const step = (now) => { const progress = Math.min((now - start) / 1200, 1); shown = Math.round(target * progress); if (progress < 1) requestAnimationFrame(step); }; requestAnimationFrame(step);"
                        x-text="shown.toLocaleString()"
                    @endif
                >{{ number_format((float) $number) }}</span>{{ $item['suffix'] ?? '' }}
            </dd>
            <p class="mt-2 text-sm font-medium opacity-80" aria-hidden="true">{{ $item['label'] ?? '' }}</p>
        </div>
    @endforeach
</dl>
