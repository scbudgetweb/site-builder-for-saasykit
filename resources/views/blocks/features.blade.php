{{-- Feature cards. Their layout comes from CardStyle::resolve(), their look from Design › Items (items.css). --}}
@php
    $columns = (int) ($block['columns'] ?? 3);
    $style = \ScBudgetWeb\SiteBuilder\Blocks\CardStyle::resolve($block['card'] ?? null, $block['design']['items'] ?? null);
@endphp

@include('site-builder::blocks.partials.heading')

<div @class([
    'sb-items grid gap-6 sm:grid-cols-2',
    'lg:grid-cols-2' => $columns === 2,
    'lg:grid-cols-3' => $columns === 3,
    'lg:grid-cols-4' => $columns === 4,
])>
    @foreach ($block['items'] ?? [] as $item)
        @php($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($item['icon'] ?? null, $style['iconSize']))

        <div class="sb-item {{ $style['card'] }}" @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif>
            @if ($icon->toHtml() !== '')
                <span class="{{ $style['iconWrap'] }}">{{ $icon }}</span>
            @endif

            <div @class(['min-w-0', 'flex-1' => $style['isRow']])>
                <h3 class="sb-item-title text-lg font-semibold">{{ $item['title'] ?? '' }}</h3>

                @if (filled($item['text'] ?? null))
                    <p class="sb-item-text block-text mt-2 text-sm leading-relaxed opacity-80">{{ $item['text'] }}</p>
                @endif

                @if (filled($item['link_url'] ?? null) && preg_match('/^(https?:\/\/|\/|#)/i', $item['link_url']))
                    <a href="{{ $item['link_url'] }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">
                        {{ filled($item['link_label'] ?? null) ? $item['link_label'] : __('Find out more') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>
