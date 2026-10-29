@php($columns = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::columns($block, 2, [1, 2, 3, 4]))

@include('site-builder::blocks.partials.heading')

<div @class([
    'grid gap-6',
    'md:grid-cols-2' => $columns >= 2,
    'lg:grid-cols-3' => $columns === 3,
    'lg:grid-cols-4' => $columns === 4,
])>
    @foreach ($block['items'] ?? [] as $item)
        @php($photoUrl = \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::imageUrl($item['photo'] ?? null))
        <figure class="sb-item flex flex-col rounded-sb-card border border-gray-200 bg-white p-6 sm:p-8 text-gray-900 shadow-sb-card">
            @if (filled($item['stars'] ?? null))
                <div class="mb-3">@include('site-builder::blocks.partials.stars', ['stars' => $item['stars']])</div>
            @endif
            <svg class="h-8 w-8 text-primary-200" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true"><path d="M9.3 6C4.7 9.1 2 13.4 2 18.5 2 22.9 4.6 26 8 26c3 0 5.2-2.3 5.2-5.1 0-2.8-2-4.9-4.6-4.9-.5 0-1.2.1-1.3.1.5-3.4 3.6-7.3 6.7-9.3L9.3 6zm16.6 0c-4.6 3.1-7.3 7.4-7.3 12.5 0 4.4 2.6 7.5 6 7.5 3 0 5.2-2.3 5.2-5.1 0-2.8-2-4.9-4.6-4.9-.5 0-1.2.1-1.3.1.5-3.4 3.6-7.3 6.7-9.3L25.9 6z"/></svg>
            <blockquote class="block-text mt-4 flex-1 text-base leading-relaxed">“{{ $item['quote'] ?? '' }}”</blockquote>
            <figcaption class="mt-6 flex items-center gap-3">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" loading="lazy" class="h-12 w-12 rounded-full object-cover">
                @endif
                <div>
                    <div class="font-semibold">{{ $item['name'] ?? '' }}</div>
                    @if (filled($item['role'] ?? null))
                        <div class="text-sm text-gray-500">{{ $item['role'] }}</div>
                    @endif
                </div>
            </figcaption>
        </figure>
    @endforeach
</div>
