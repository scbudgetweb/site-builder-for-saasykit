{{-- Feature cards. Their look (background, border, icon position and colours) comes from CardStyle::resolve(). --}}
@php
    $columns = (int) ($block['columns'] ?? 3);
    $style = \ScBudgetWeb\SiteBuilder\Blocks\CardStyle::resolve($block['card'] ?? null);
@endphp

@include('site-builder::blocks.partials.heading')

<div @class([
    'grid gap-6 sm:grid-cols-2',
    'lg:grid-cols-2' => $columns === 2,
    'lg:grid-cols-3' => $columns === 3,
    'lg:grid-cols-4' => $columns === 4,
])>
    @foreach ($block['items'] ?? [] as $item)
        @php($icon = \ScBudgetWeb\SiteBuilder\Support\IconLibrary::render($item['icon'] ?? null, $style['iconSize']))

        <div class="sb-item {{ $style['card'] }}" @if ($style['cardStyle'] !== '') style="{{ $style['cardStyle'] }}" @endif @if ($style['textScheme']) data-text-scheme="{{ $style['textScheme'] }}" @endif>
            @if ($icon->toHtml() !== '')
                <span class="{{ $style['iconWrap'] }}" @if ($style['iconStyle'] !== '') style="{{ $style['iconStyle'] }}" @endif>{{ $icon }}</span>
            @endif

            <div @class(['min-w-0', 'flex-1' => $style['isRow']])>
                <h3 class="text-lg font-semibold" @if ($style['titleStyle'] !== '') style="{{ $style['titleStyle'] }}" @endif>{{ $item['title'] ?? '' }}</h3>

                @if (filled($item['text'] ?? null))
                    <p class="block-text mt-2 text-sm leading-relaxed opacity-80" @if ($style['textStyle'] !== '') style="{{ $style['textStyle'] }}" @endif>{{ $item['text'] }}</p>
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
