{{--
    A Section: its rows ($rows, with each column's blocks already rendered), one under another. A row is stacked, or
    in columns side by side from tablet width and stacked on phones; each column holds its blocks one under another,
    with its own background, padding, border and alignment when it has them. A column with one block and no settings
    is marked up as before columns held several (1.4), so those rows look the same. An empty column keeps its place.
    Classes come from SectionBlock's whitelists.
--}}
@php
    $layouts = \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::LAYOUTS;
@endphp

<div class="{{ $gap[1] }}">
    @foreach ($rows as $row)
        @continue(array_merge(...array_column($row['columns'], 'blocks')) === [])

        @php
            $layout = $layouts[$row['layout']];
            $spans = $layout[2];
            $isStacked = $row['layout'] === 'stacked';
            $columnGap = $row['gap'] ?? $gap[2];
            // Stacked: each block is a cell of its own. Side by side: each column is one cell.
            $cells = $isStacked
                ? array_map(fn (string $block): array => ['blocks' => [$block], 'class' => '', 'style' => '', 'textScheme' => null], $row['columns'][0]['blocks'] ?? [])
                : $row['columns'];
        @endphp

        <div @class([
            'grid grid-cols-1',
            $columnGap,
            $layout[1],
            \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::ALIGNMENTS[$row['align']][1] => ! $isStacked,
            // Same height: each column fills the row and its buttons line up along the bottom (site-builder.css).
            'sb-row-stretch' => ! $isStacked && $row['align'] === 'stretch',
            $row['class'],
        ]) data-row-layout="{{ $row['layout'] }}" @if ($row['style'] !== '') style="{{ $row['style'] }}" @endif>
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
                @endphp
                <div class="{{ $cellClass }}"@if (! $isStacked) data-column="{{ $loop->iteration }}"@endif @if ($cell['style'] !== '') style="{{ $cell['style'] }}"@endif @if ($cell['textScheme']) data-text-scheme="{{ $cell['textScheme'] }}"@endif>@foreach ($cell['blocks'] as $innerBlock){!! $innerBlock !!}@endforeach</div>
            @endforeach
        </div>
    @endforeach
</div>
