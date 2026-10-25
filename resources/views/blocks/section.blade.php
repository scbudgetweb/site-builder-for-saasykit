{{--
    A Section: its rows ($rows, with each row's blocks already rendered), one under another. A row's blocks are stacked
    or in columns, side by side from tablet width and stacked on phones; they fill the columns in order, one block per
    column, wrapping onto a new line when there are more blocks than columns. Classes come from SectionBlock's whitelists.
--}}
@php
    $layouts = \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::LAYOUTS;
@endphp

<div class="{{ $gap[1] }}">
    @foreach ($rows as $row)
        @continue($row['blocks'] === [])

        @php
            $layout = $layouts[$row['layout']];
            $spans = $layout[2];
            $isStacked = $row['layout'] === 'stacked';
        @endphp

        <div @class([
            'grid grid-cols-1',
            $gap[2],
            $layout[1],
            \ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock::ALIGNMENTS[$row['align']][1] => ! $isStacked,
            // Same height: each block fills its column and its buttons line up along the bottom (site-builder.css).
            'sb-row-stretch' => ! $isStacked && $row['align'] === 'stretch',
            $row['class'],
        ]) data-row-layout="{{ $row['layout'] }}" @if ($row['style'] !== '') style="{{ $row['style'] }}" @endif>
            @foreach ($row['blocks'] as $innerBlock)
                <div @class([
                    'min-w-0',
                    $spans[$loop->index % max(1, count($spans))] ?? '',
                    // Last block first on phones: reverse the order below tablet width, then back to normal.
                    'max-md:order-first' => $row['reverse'] && ! $isStacked && $loop->last,
                ])>{!! $innerBlock !!}</div>
            @endforeach
        </div>
    @endforeach
</div>
