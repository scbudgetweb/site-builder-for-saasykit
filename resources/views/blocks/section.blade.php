{{--
    A Section: its rows ($rows, with each column's blocks already rendered), one under another. A row is stacked, or
    in columns side by side from tablet width and stacked on phones; each column holds its blocks one under another,
    with its own background, padding, border and alignment when it has them. A column with one block and no settings
    is marked up as before columns held several (1.4), so those rows look the same. An empty column keeps its place.
    Classes come from SectionBlock's whitelists.

    A row or column can also have an anchor (id), a background image with its overlay, and a link over the whole of it
    (under its blocks' own buttons and links), drawn as for a block (wrapper view). With an image or a link it's a
    layer of its own (sb-node-layered), so they sit behind its blocks.

    On the visual builder's canvas ($marksNodes), rows and columns carry data-sb-node and data-sb-uid (a stacked row's
    one column is named on the row, as data-sb-column), and empty rows and columns show, so blocks can be added to them.
--}}
@php
    $layouts = \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::LAYOUTS;
    $marksNodes ??= false;
    // A row's or column's background image (with its overlay) and its link, as the first things inside it.
    $layers = fn (array $node): string => (($node['image'] ?? null)
            ? '<div class="absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">'
                .'<img src="'.e($node['image']).'" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">'
                .(($node['overlayStyle'] ?? null) ? '<div class="absolute inset-0" style="'.e($node['overlayStyle']).'"></div>' : '')
                .'</div>'
            : '')
        .(($node['link'] ?? null)
            ? '<a href="'.e($node['link']['url']).'" class="sb-block-link" aria-label="'.e(__('Open link')).'"'.($node['link']['newTab'] ? ' target="_blank" rel="noopener"' : '').'></a>'
            : '');
@endphp

<div class="{{ $gap[1] }}">
    @foreach ($rows as $row)
        @continue(! $marksNodes && array_merge(...array_column($row['columns'], 'blocks')) === [])

        @php
            $layout = $layouts[$row['layout']];
            $spans = $layout[2];
            $isStacked = $row['layout'] === 'stacked';
            $columnGap = $row['gap'] ?? $gap[2];
            // Stacked: each block is a cell of its own, unless the one column has settings of its own (then it's one
            // cell, as a side-by-side column). Side by side: each column is one cell.
            $column = $row['columns'][0] ?? null;
            $hasColumnLook = $isStacked && $column !== null && ($column['class'] !== '' || $column['style'] !== '' || ($column['textScheme'] ?? null) || ($column['id'] ?? null) || ($column['link'] ?? null) || ($column['image'] ?? null));
            $cells = $isStacked && ! $hasColumnLook
                ? array_map(fn (string $block): array => ['blocks' => [$block], 'class' => '', 'style' => '', 'textScheme' => null], $row['columns'][0]['blocks'] ?? [])
                : $row['columns'];
            // An empty stacked row on the canvas: one empty cell to click into.
            if ($marksNodes && $cells === []) {
                $cells = [['blocks' => [], 'class' => '', 'style' => '', 'textScheme' => null]];
            }
            $rowAttributes = $marksNodes
                ? ' data-sb-node="row" data-sb-uid="'.e($row['uid'] ?? '').'"'.($isStacked ? ' data-sb-column="'.e($row['columns'][0]['uid'] ?? '').'"' : '')
                : '';
        @endphp

        <div @class([
            'grid grid-cols-1',
            $columnGap,
            $layout[1],
            \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::ALIGNMENTS[$row['align']][1] => ! $isStacked,
            // Same height: each column fills the row and its buttons line up along the bottom (site-builder.css).
            'sb-row-stretch' => ! $isStacked && $row['align'] === 'stretch',
            $row['class'],
        ]) @if ($row['id'] ?? null) id="{{ $row['id'] }}" @endif data-row-layout="{{ $row['layout'] }}" @if ($row['style'] !== '') style="{{ $row['style'] }}" @endif @if ($row['textScheme'] ?? null) data-text-scheme="{{ $row['textScheme'] }}" @endif{!! $rowAttributes !!}>
            {!! $layers($row) !!}
            @foreach ($cells as $cell)
                @php
                    $cellClass = implode(' ', array_filter([
                        'min-w-0',
                        $spans[$loop->index] ?? '',
                        // Last column first on phones: reverse the order below tablet width, then back to normal.
                        $row['reverse'] && ! $isStacked && $loop->last ? 'max-md:order-first' : '',
                        // Several blocks in a column: one under another, as far apart as the columns.
                        count($cell['blocks']) > 1 ? 'flex flex-col '.$columnGap : '',
                        $cell['class'],
                    ]));
                    // Canvas only, so a page's HTML is unchanged.
                    $canvasAttributes = $marksNodes && ! $isStacked ? ' data-sb-node="column" data-sb-uid="'.e($cell['uid'] ?? '').'"' : '';
                    $emptyPlaceholder = $marksNodes && $cell['blocks'] === [] ? '<div class="sb-canvas-empty" data-sb-empty>'.e(__('Empty')).'</div>' : '';
                @endphp
                <div class="{{ $cellClass }}"@if ($cell['id'] ?? null) id="{{ $cell['id'] }}"@endif @if (! $isStacked) data-column="{{ $loop->iteration }}"@endif @if ($cell['style'] !== '') style="{{ $cell['style'] }}"@endif @if ($cell['textScheme']) data-text-scheme="{{ $cell['textScheme'] }}"@endif{!! $canvasAttributes !!}>{!! $layers($cell) !!}@foreach ($cell['blocks'] as $innerBlock){!! $innerBlock !!}@endforeach{!! $emptyPlaceholder !!}</div>
            @endforeach
        </div>
    @endforeach
</div>
