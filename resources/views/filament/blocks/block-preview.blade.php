{{--
    The admin card for every block (Builder::blockPreviews()). Filament passes the block's fields as variables of the
    same name, so this shows whatever the block has: its name, heading, a text excerpt, the first image, how many
    items it holds, and tags for its styling. Click the card to edit the block.
--}}
@php
    $title = $heading ?? $title ?? $question ?? null;
    $title = is_string($title) ? $title : null;

    $excerptSource = $subtitle ?? $intro ?? $text ?? $content ?? null;
    $excerpt = is_string($excerptSource) ? \Illuminate\Support\Str::limit(trim(strip_tags($excerptSource)), 160) : null;

    $imageUrl = null;
    foreach ([$image ?? null, $poster ?? null] as $candidate) {
        $imageUrl ??= \ScBudgetWeb\SiteBuilder\Blocks\PageBlock::imageUrl(is_array($candidate) ? collect($candidate)->first(fn ($value) => is_string($value)) : $candidate);
    }

    // How many items the block holds (features, cards, people, images, logos…), or else its buttons.
    $itemCount = collect([$items ?? null, $cards ?? null, $members ?? null, $images ?? null, $logos ?? null, $buttons ?? null])
        ->filter(fn ($list) => is_array($list) && $list !== [])
        ->map(fn ($list) => count($list))
        ->first() ?? 0;

    // A Section: each row's layout and the blocks in it.
    $sectionRows = isset($rows) && is_array($rows)
        ? array_map(fn (array $row): string => \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::rowLabel($row), array_values(array_filter($rows, 'is_array')))
        : null;

    // A linked library block: the library item's name and kind of block.
    $libraryItem = isset($saved_block_id) && is_numeric($saved_block_id) ? \ScBudgetWeb\SiteBuilder\Models\SavedBlock::query()->find((int) $saved_block_id) : null;
    if ($libraryItem) {
        $title = $libraryItem->name;
        $excerpt = __(':block, linked to the block library: edit it there to change every page using it.', ['block' => app(\ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry::class)->labelFor($libraryItem->type)]);
    } elseif (isset($saved_block_id) && filled($saved_block_id)) {
        $excerpt = __('This library block no longer exists, so nothing shows here. Remove the block or choose another.');
    }

    // Styling tags (background, panel, sizes, anchor, classes); the Banner keeps its CSS classes outside Appearance.
    $styleTags = \ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance::summary(is_array($appearance ?? null) ? $appearance : null, $css_class ?? null);
@endphp

<div class="flex items-start gap-4 p-4">
    @if ($imageUrl)
        <img src="{{ $imageUrl }}" alt="" class="h-16 w-24 shrink-0 rounded-md object-cover">
    @endif

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                {{ $preview_label ?? __('Block') }}
            </span>
            @if ($itemCount)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ trans_choice(':count item|:count items', $itemCount, ['count' => $itemCount]) }}</span>
            @endif
            @if ($libraryItem)
                <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 ring-1 ring-success-600/10 dark:bg-success-400/10 dark:text-success-300">{{ __('Linked') }}</span>
            @endif
            @foreach ($styleTags as $tag)
                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300">{{ $tag }}</span>
            @endforeach
        </div>

        @if ($title)
            <p class="mt-1.5 truncate text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
        @endif

        @if ($excerpt)
            <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{{ $excerpt }}</p>
        @endif

        @if ($sectionRows !== null)
            @if ($sectionRows === [])
                <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">{{ __('Empty: click to add rows of blocks.') }}</p>
            @else
                <ol class="mt-1.5 space-y-0.5 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($sectionRows as $rowLabel)
                        <li>{{ __('Row :number', ['number' => $loop->iteration]) }} · {{ $rowLabel }}</li>
                    @endforeach
                </ol>
            @endif
        @endif

        @if (! $title && ! $excerpt && ! $imageUrl && $sectionRows === null)
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ __('Click to edit this block.') }}</p>
        @endif
    </div>
</div>
