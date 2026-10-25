<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * One styled area (the shared background, panel and spacing) holding rows of blocks (D0a-14). Each row has its own
 * layout: stacked, or columns side by side from tablet width (stacked on phones), which its blocks fill in order,
 * one block per column. Sections go one level deep: no Hero, Banner or Section inside one.
 *
 * The renderer (PageRenderer) renders each row's blocks inside the section, passing down its text colour and heading
 * decoration, and gives this view the rendered rows.
 */
class SectionBlock extends PageBlock
{
    public const NAME = 'section';

    /** Layout => [label, grid classes, column spans by position (cycled)]. */
    public const LAYOUTS = [
        'stacked' => ['Stacked (one under another)', '', []],
        '2' => ['2 equal columns', 'md:grid-cols-2', []],
        '3' => ['3 equal columns', 'md:grid-cols-3', []],
        '4' => ['4 equal columns', 'md:grid-cols-2 lg:grid-cols-4', []],
        '1-2' => ['Narrow + wide (⅓ + ⅔)', 'md:grid-cols-3', ['md:col-span-1', 'md:col-span-2']],
        '2-1' => ['Wide + narrow (⅔ + ⅓)', 'md:grid-cols-3', ['md:col-span-2', 'md:col-span-1']],
    ];

    /** Vertical alignment => [label, classes]. */
    public const ALIGNMENTS = [
        'start' => ['Top', 'items-start'],
        'center' => ['Middle', 'items-center'],
        'end' => ['Bottom', 'items-end'],
        'stretch' => ['Same height', 'items-stretch'],
    ];

    /** Space between rows and between columns => [label, row gap, column gap]. */
    public const GAPS = [
        'small' => ['Small', 'space-y-6', 'gap-6'],
        'medium' => ['Medium', 'space-y-10 sm:space-y-12', 'gap-8 lg:gap-12'],
        'large' => ['Large', 'space-y-14 sm:space-y-20', 'gap-10 lg:gap-16'],
    ];

    /** A row's padding => [label, classes]; "auto" pads a row with a border or shadow, so its blocks don't touch them. */
    public const ROW_PADDINGS = [
        'auto' => ['Automatic', null],
        'none' => ['None', ''],
        'small' => ['Small', 'p-4 sm:p-6'],
        'medium' => ['Medium', 'p-6 sm:p-8'],
        'large' => ['Large', 'p-8 sm:p-12'],
    ];

    /** A row's corners => [label, classes]. */
    public const ROW_CORNERS = [
        'none' => ['None', ''],
        'site' => ['Site style (Branding)', 'rounded-sb-card'],
        'small' => ['Small', 'rounded-md'],
        'medium' => ['Medium', 'rounded-xl'],
        'large' => ['Large', 'rounded-3xl'],
    ];

    /** Block types that can't go inside a section. */
    public const EXCLUDED_BLOCKS = ['hero', 'banner', self::NAME];

    public static function name(): string
    {
        return self::NAME;
    }

    public static function label(): string
    {
        return __('Section (rows of blocks)');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSquares2x2;
    }

    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    public static function contentTabLabel(): string
    {
        return __('Rows');
    }

    protected static function fields(): array
    {
        $registry = app(BlockRegistry::class);

        return [
            Repeater::make('rows')
                ->label(__('Rows'))
                ->helperText(__('Each row has its own layout. Side by side, the row\'s blocks fill its columns in order, one block per column; to put two blocks in one column, add another row.'))
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('layout')
                            ->label(__('Layout'))
                            ->options(array_map(fn (array $layout): string => __($layout[0]), self::LAYOUTS))
                            ->default('2')
                            ->selectablePlaceholder(false)
                            ->live(),
                        Select::make('align')
                            ->label(__('Line up blocks at the'))
                            ->options(array_map(fn (array $alignment): string => __($alignment[0]), self::ALIGNMENTS))
                            ->default('start')
                            ->selectablePlaceholder(false),
                        Toggle::make('reverse_on_phones')
                            ->label(__('Last block first on phones'))
                            ->helperText(__('E.g. so an image on the right shows above its text.'))
                            ->inline(false),
                    ]),
                    Section::make(__('Row style'))
                        ->description(__('A border, shadow and corners around this row.'))
                        ->collapsible()
                        ->collapsed()
                        ->compact()
                        ->columns(2)
                        ->schema([
                            ...BorderShadow::borderFields(),
                            ...BorderShadow::shadowFields(),
                            Select::make('corners')
                                ->label(__('Corners'))
                                ->options(array_map(fn (array $corners): string => __($corners[0]), self::ROW_CORNERS))
                                ->default('none')
                                ->selectablePlaceholder(false),
                            Select::make('padding')
                                ->label(__('Space inside'))
                                ->options(array_map(fn (array $padding): string => __($padding[0]), self::ROW_PADDINGS))
                                ->default('auto')
                                ->helperText(__('Automatic: some space when the row has a border or shadow.'))
                                ->selectablePlaceholder(false),
                        ]),
                    $registry->editor(Builder::make('blocks'), $registry->sectionBuilderBlocks())
                        ->label(__('Blocks in this row'))
                        ->minItems(1)
                        ->addActionLabel(__('Add block to this row')),
                ])
                ->minItems(1)
                ->defaultItems(1)
                ->collapsible()
                ->cloneable()
                ->reorderableWithButtons()
                ->itemLabel(fn (array $state): string => self::rowLabel($state))
                ->addActionLabel(__('Add row')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('gap')
                ->label(__('Space between rows and columns'))
                ->options(array_map(fn (array $gap): string => __($gap[0]), self::GAPS))
                ->default('medium')
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * The section's rows that have the expected shape, each with only its well-formed blocks.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{layout: string, align: string, reverse: bool, class: string, style: string, blocks: array<int, array{type: string, data: array<string, mixed>}>}>
     */
    public static function rows(array $data): array
    {
        return collect(is_array($data['rows'] ?? null) ? $data['rows'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'layout' => static::pick($row['layout'] ?? null, self::LAYOUTS, 'stacked'),
                'align' => static::pick($row['align'] ?? null, self::ALIGNMENTS, 'start'),
                'reverse' => (bool) ($row['reverse_on_phones'] ?? false),
                'class' => self::rowClass($row),
                'style' => BorderShadow::style($row),
                'blocks' => array_values(array_filter(
                    is_array($row['blocks'] ?? null) ? $row['blocks'] : [],
                    fn (mixed $block): bool => is_array($block) && is_string($block['type'] ?? null) && is_array($block['data'] ?? null),
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * The row's corners and padding (Row style).
     *
     * @param  array<string, mixed>  $row
     */
    private static function rowClass(array $row): string
    {
        $padding = static::pick($row['padding'] ?? null, self::ROW_PADDINGS, 'auto');
        $isFramed = BorderShadow::style($row) !== '';

        return trim(implode(' ', array_filter([
            self::ROW_CORNERS[static::pick($row['corners'] ?? null, self::ROW_CORNERS, 'none')][1],
            $padding === 'auto' ? ($isFramed ? self::ROW_PADDINGS['medium'][1] : '') : self::ROW_PADDINGS[$padding][1],
        ])));
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'gap' => self::GAPS[static::pick($data['gap'] ?? null, self::GAPS, 'medium')],
        ];
    }

    /**
     * "Section: 2 rows, 5 blocks".
     *
     * @param  array<string, mixed>  $state
     */
    protected static function itemLabel(array $state): string
    {
        $rows = self::rows($state);
        $blocks = array_sum(array_map(fn (array $row): int => count($row['blocks']), $rows));

        return __('Section: :rows, :blocks', [
            'rows' => trans_choice(':count row|:count rows', count($rows), ['count' => count($rows)]),
            'blocks' => trans_choice(':count block|:count blocks', $blocks, ['count' => $blocks]),
        ]);
    }

    /**
     * "2 equal columns: Image + text, Text", for a row in the editor.
     *
     * @param  array<string, mixed>  $state
     */
    public static function rowLabel(array $state): string
    {
        $row = self::rows(['rows' => [$state]])[0] ?? ['layout' => 'stacked', 'blocks' => []];
        $registry = app(BlockRegistry::class);
        $names = array_map(fn (array $block): string => $registry->labelFor($block['type']), $row['blocks']);

        return __(self::LAYOUTS[$row['layout']][0]).($names === [] ? '' : ': '.implode(', ', $names));
    }
}
