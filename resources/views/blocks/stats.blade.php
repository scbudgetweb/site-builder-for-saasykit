@php($animate = (bool) ($block['animate'] ?? true))

@include('site-builder::blocks.partials.heading')

<dl @class([
    'sb-items grid gap-8 text-center',
    'sm:grid-cols-2' => count($items) > 1,
    'lg:grid-cols-3' => count($items) === 3 || count($items) > 4,
    'lg:grid-cols-4' => count($items) === 4,
])>
    @foreach ($items as $item)
        @php($number = $item['number'] ?? 0)
        <div class="sb-item">
            @if (($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($item['icon'] ?? null, 'h-8 w-8'))->toHtml() !== '')
                <span @class(['sb-item-icon mb-3 inline-flex', $isDark ? 'text-inherit' : 'text-primary-600'])>{{ $icon }}</span>
            @endif
            <dt class="sr-only">{{ $item['label'] ?? '' }}</dt>
            <dd @class(['sb-item-value text-4xl sm:text-5xl font-extrabold', $isDark ? 'text-inherit' : 'text-primary-600'])>
                {{ $item['prefix'] ?? '' }}<span
                    @if ($animate)
                        x-data="{ shown: 0, target: {{ (float) $number }} }"
                        x-intersect.once="if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { shown = target; return; } const start = performance.now(); const step = (now) => { const progress = Math.min((now - start) / 1200, 1); shown = Math.round(target * progress); if (progress < 1) requestAnimationFrame(step); }; requestAnimationFrame(step);"
                        x-text="shown.toLocaleString()"
                    @endif
                >{{ number_format((float) $number) }}</span>{{ $item['suffix'] ?? '' }}
            </dd>
            <p class="sb-item-label mt-2 text-sm font-medium opacity-80" aria-hidden="true">{{ $item['label'] ?? '' }}</p>
        </div>
    @endforeach
</dl>
