<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\BlockSchema;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\Design\DesignOutput;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\SettingsPanel;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * One styled area (the shared background, panel and spacing) holding rows of blocks (D0a-14). Each row has its own
 * layout: stacked, or columns side by side from tablet width (stacked on phones). Each column holds its own blocks,
 * one under another, so a column can hold a heading, text and an image beside one big image in the next. Sections go
 * one level deep: no Hero, Banner or Section inside one.
 *
 * A row is stored as {uid, layout, align, gap, …row style, columns: [{uid, blocks: […], …column settings}, …]}, with as
 * many columns as its layout has (one when stacked). A column's settings (background, text colour, padding, corners,
 * border, shadow and alignment) sit beside its blocks, as a row's do; a one-column row's column has them too. Rows saved by 1.4
 * and earlier kept one `blocks` list that filled the columns in order; normaliseRow() reads those, and the
 * StoredBlocks cast converts them whenever a page or library item is loaded (BlockSchema).
 *
 * The renderer (PageRenderer) renders each column's blocks inside the section, passing down its text colour and
 * heading decoration, and gives this view the rendered rows.
 */
class SectionBlock extends PageBlock
{
    public const NAME = 'section';

    /**
     * Layout => [label, grid classes, column spans by position]. Uneven layouts are named by their parts, e.g. "1-3" is
     * one part and three parts (¼ + ¾). Never rename a key once pages use it.
     */
    public const LAYOUTS = [
        'stacked' => ['One column (blocks one under another)', '', []],
        '2' => ['Two equal columns', 'md:grid-cols-2', []],
        '1-2' => ['Narrow left, wide right (⅓ + ⅔)', 'md:grid-cols-3', ['md:col-span-1', 'md:col-span-2']],
        '2-1' => ['Wide left, narrow right (⅔ + ⅓)', 'md:grid-cols-3', ['md:col-span-2', 'md:col-span-1']],
        '1-3' => ['Thin left, wide right (¼ + ¾)', 'md:grid-cols-4', ['md:col-span-1', 'md:col-span-3']],
        '3-1' => ['Wide left, thin right (¾ + ¼)', 'md:grid-cols-4', ['md:col-span-3', 'md:col-span-1']],
        '2-3' => ['Left a little narrower (40% + 60%)', 'md:grid-cols-5', ['md:col-span-2', 'md:col-span-3']],
        '3-2' => ['Left a little wider (60% + 40%)', 'md:grid-cols-5', ['md:col-span-3', 'md:col-span-2']],
        '3' => ['Three equal columns', 'md:grid-cols-3', []],
        '1-2-1' => ['Wide middle, thin sides (¼ + ½ + ¼)', 'md:grid-cols-4', ['md:col-span-1', 'md:col-span-2', 'md:col-span-1']],
        '4' => ['Four equal columns', 'md:grid-cols-2 lg:grid-cols-4', []],
        '5' => ['Five equal columns', 'md:grid-cols-3 lg:grid-cols-5', []],
    ];

    /** Layout => how many columns it has. */
    public const COLUMN_COUNTS = [
        'stacked' => 1,
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '1-2' => 2,
        '2-1' => 2,
        '1-3' => 2,
        '3-1' => 2,
        '2-3' => 2,
        '3-2' => 2,
        '1-2-1' => 3,
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

    /** A row's own space between its columns (and between blocks in a column) => [label, classes]; "section" uses the section's. */
    public const ROW_GAPS = [
        'section' => ['Same as the section', null],
        'none' => ['None (columns touch)', 'gap-0'],
        'small' => ['Small', 'gap-6'],
        'medium' => ['Medium', 'gap-8 lg:gap-12'],
        'large' => ['Large', 'gap-10 lg:gap-16'],
    ];

    /** A column's own vertical alignment => [label, classes]; "row" follows the row's. */
    public const COLUMN_ALIGNMENTS = [
        'row' => ['Same as the row', ''],
        'start' => ['Top', 'self-start'],
        'center' => ['Middle', 'self-center'],
        'end' => ['Bottom', 'self-end'],
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

    /**
     * A Section, row or column has no text settings (Heading, Body, Link and List text, text colour on
     * hover): only the blocks in it have those.
     */
    private const STRUCTURE_DESIGN_WITHOUT = ['typography', 'borders.hover_text'];

    /** A row's: a Section's, less Dividers (a Section's edges only). */
    private const ROW_DESIGN_WITHOUT = [...self::STRUCTURE_DESIGN_WITHOUT, 'dividers'];

    /** A column's: a row's, less Sizing and margin (it fills its share of the row; padding only). */
    private const COLUMN_DESIGN_WITHOUT = [
        ...self::ROW_DESIGN_WITHOUT,
        'spacing.margin', 'spacing.width', 'spacing.max_width', 'spacing.align', 'spacing.min_height', 'spacing.height',
        'spacing.max_height', 'spacing.vertical_align',
    ];

    public static function structureLevel(): string
    {
        return 'section';
    }

    public static function designWithout(): array
    {
        return self::STRUCTURE_DESIGN_WITHOUT;
    }

    /** Without the text settings a Section used to have (heading alignment, decoration and size, text size). */
    public static function shownAppearance(mixed $appearance): array
    {
        return array_diff_key(parent::shownAppearance($appearance), array_flip(['heading_align', 'heading_decoration', 'heading_size', 'text_size']));
    }

    public static function contentTabLabel(): string
    {
        return self::$isVisualPanel ? parent::contentTabLabel() : __('Rows');
    }

    /** While building the visual builder's panel (visualPanelSchema()): no rows, only the Section's own settings. */
    private static bool $isVisualPanel = false;

    /**
     * The Section's panel in the visual builder: the Section's own settings only. Its rows are on the
     * canvas, each with its own panel, so they aren't part of this one's data (VisualEditor keeps them from the draft).
     *
     * @return array<Component|Field>
     */
    public static function visualPanelSchema(): array
    {
        self::$isVisualPanel = true;

        try {
            return static::editorSchema(false);
        } finally {
            self::$isVisualPanel = false;
        }
    }

    /**
     * The visual builder's list of a row's columns, each opening its settings (filament/blocks/panel-structure).
     */
    public static function columnsView(): View
    {
        return View::make('site-builder::filament.blocks.panel-structure')->columnSpanFull();
    }

    protected static function fields(): array
    {
        if (self::$isVisualPanel) {
            return [];
        }

        $registry = app(BlockRegistry::class);

        return [
            Repeater::make('rows')
                ->label(__('Rows'))
                ->extraAttributes(['data-sb-level' => 'row'])
                ->helperText(__('A section is a band across the page, built from rows. Choose how each row is split into columns, then add blocks (text, images, buttons…) to each column. On phones, columns sit one under another.'))
                ->schema([
                    // The layout stays in the form (it sets how many columns there are); every other setting is in
                    // the row's own panel (⚙), the same one the visual builder opens.
                    self::rowLayoutFields()[0],
                    ...self::keptFields(self::rowSettingsPanel(inForm: true), except: ['layout', 'columns']),
                    Repeater::make('columns')
                        ->extraAttributes(['data-sb-level' => 'column'])
                        ->label(fn (Get $get): string => self::columnCount($get('layout')) > 1 ? __('Columns') : __('Blocks in this row'))
                        ->schema([
                            // Its settings are in its own panel (⚙), the same one the visual builder opens.
                            ...self::keptFields(self::columnSettingsPanel(), except: ['blocks']),
                            $registry->editor(Builder::make('blocks'), $registry->sectionBuilderBlocks())
                                ->hiddenLabel()
                                ->addActionLabel(fn (Builder $component): string => self::columnPosition($component)['count'] > 1
                                    ? __('Add block to column :number', ['number' => self::columnPosition($component)['number']])
                                    : __('Add block to this row'))
                                ->extraItemActions(self::moveActions()),
                        ])
                        ->default([['blocks' => []], ['blocks' => []]])
                        ->extraItemActions([self::settingsAction('columnSettings', __('Column settings'), fn (): array => self::columnSettingsPanel(), 'blocks', 'column')])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(fn (Get $get): bool => self::columnCount($get('layout')) > 1)
                        ->reorderableWithButtons()
                        // Columns sit side by side, so moving one is left and right.
                        ->moveUpAction(fn (Action $action): Action => $action->icon(Heroicon::ArrowLeft)->label(__('Swap with the column on the left'))->tooltip(__('Swap with the column on the left')))
                        ->moveDownAction(fn (Action $action): Action => $action->icon(Heroicon::ArrowRight)->label(__('Swap with the column on the right'))->tooltip(__('Swap with the column on the right')))
                        ->reorderAction(fn (Action $action): Action => $action->tooltip(__('Drag to move this column')))
                        ->itemLabel(fn (Repeater $component, string $key): string => self::columnCount($component->getContainer()->getRawState()['layout'] ?? null) > 1
                            ? __('Column :number', ['number' => array_search($key, array_keys((array) $component->getRawState()), true) + 1])
                            : __('Blocks'))
                        ->grid(fn (Get $get): array => ['md' => min(2, self::columnCount($get('layout'))), 'xl' => self::columnCount($get('layout'))]),
                ])
                ->extraItemActions([self::settingsAction('rowSettings', __('Row settings'), fn (): array => self::rowSettingsPanel(inForm: true), 'columns', 'row')])
                ->minItems(1)
                ->defaultItems(1)
                ->collapsible()
                ->cloneable()
                ->reorderableWithButtons()
                ->moveUpAction(fn (Action $action): Action => $action->tooltip(__('Move this row up')))
                ->moveDownAction(fn (Action $action): Action => $action->tooltip(__('Move this row down')))
                ->reorderAction(fn (Action $action): Action => $action->tooltip(__('Drag to move this row')))
                ->cloneAction(fn (Action $action): Action => $action->tooltip(__('Duplicate this row')))
                ->deleteAction(fn (Action $action): Action => $action->tooltip(__('Delete this row')))
                ->collapseAction(fn (Action $action): Action => $action->tooltip(__('Collapse')))
                ->expandAction(fn (Action $action): Action => $action->tooltip(__('Expand')))
                ->itemLabel(fn (array $state, Repeater $component, string $key): string => __('Row :number', ['number' => array_search($key, array_keys((array) $component->getRawState()), true) + 1]).' · '.self::rowLabel($state))
                ->addActionLabel(__('Add row')),
        ];
    }

    /**
     * A row's or column's ⚙ in the Section's form: opens its settings panel, the same one the visual builder opens
     * (rowSettingsPanel(), columnSettingsPanel()), filled from the item, and saves it back into the item. $contents is
     * the item's own list (columns or blocks), which the panel leaves alone.
     *
     * @param  Closure(): array<Component>  $panel
     */
    private static function settingsAction(string $name, string $heading, Closure $panel, string $contents, string $level): Action
    {
        return Action::make($name)
            ->extraModalWindowAttributes(['data-sb-level' => $level])
            ->label($heading)
            ->tooltip($heading)
            ->icon(Heroicon::Cog6Tooth)
            ->iconButton()
            ->color('gray')
            ->modalHeading($heading)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Save changes'))
            ->fillForm(fn (array $arguments, Repeater $component): array => array_diff_key(
                (array) ($component->getRawState()[$arguments['item'] ?? ''] ?? []),
                [$contents => true, 'uid' => true],
            ))
            ->schema($panel)
            ->action(function (array $arguments, array $data, Repeater $component) use ($contents): void {
                $state = (array) $component->getRawState();
                $key = $arguments['item'] ?? null;

                if (! is_string($key) || ! is_array($state[$key] ?? null)) {
                    return;
                }

                $state[$key] = [...$state[$key], ...array_diff_key($data, [$contents => true, 'uid' => true])];
                $component->rawState($state);
                $component->callAfterStateUpdated();
            });
    }

    /**
     * Hidden fields for every setting a panel saves, so a row or column in the Section's form keeps its settings
     * (set in its ⚙ panel) when the form is saved. Found from the panel, so a setting added to it later is kept too.
     *
     * @param  array<Component>  $panel
     * @param  array<int, string>  $except  settings the form shows itself, or the item's own list
     * @return array<int, Hidden>
     */
    private static function keptFields(array $panel, array $except): array
    {
        $keys = [];
        $walk = function (array $components) use (&$walk, &$keys): void {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $keys[] = Str::before($component->getName(), '.');
                } elseif ($component instanceof Component && filled($statePath = $component->getStatePath(isAbsolute: false))) {
                    $keys[] = Str::before($statePath, '.');
                } elseif ($component instanceof Component) {
                    $children = $component->getDefaultChildComponents();
                    $walk(is_array($children) ? $children : []);
                }
            }
        };
        $walk($panel);

        return array_map(
            fn (string $key): Hidden => Hidden::make($key),
            array_values(array_diff(array_unique(array_filter($keys)), $except)),
        );
    }

    /**
     * The toggles a row's and a column's panels share with a block's: Link and Admin label on Content,
     * CSS ID & classes on Advanced.
     *
     * @return array<int, PanelToggle>
     */
    private static function nodeToggles(string $noun): array
    {
        $grid = fn (array $fields): Grid => Grid::make(['default' => 1, 'sm' => 2])->schema($fields);

        return [
            PanelToggle::content('link', __('Link'), [$grid([
                TextInput::make('link_url')
                    ->label(__('Link the whole :noun to', ['noun' => $noun]))
                    ->hintIcon(self::HELP_ICON, __('Clicking anywhere on it goes here. Its own buttons and links still go to theirs.'))
                    ->helperText(__('A page on this site (/about-us), a part of this page (#pricing) or a full web address (https://…).'))
                    ->maxLength(500)
                    ->regex('/^(https?:\/\/|\/|#)/i')
                    ->validationMessages(['regex' => __('Start the link with https://, / or #.')]),
                Toggle::make('link_new_tab')
                    ->label(__('Open in a new tab'))
                    ->inline(false),
            ])], 70),
            PanelToggle::content('admin_label', __('Admin label'), [
                TextInput::make('admin_label')
                    ->label(__('Admin label'))
                    ->hintIcon(self::HELP_ICON, __('Your own name for it, shown in the builder so it\'s easy to find. Visitors never see it.'))
                    ->maxLength(60),
            ], 99),
            PanelToggle::advanced('classes', __('CSS ID & classes'), [$grid([
                TextInput::make('anchor')
                    ->label(__('Anchor ID'))
                    ->hintIcon(self::HELP_ICON, __('A short name for this part of the page, so a button or menu link can jump straight to it.'))
                    ->alphaDash()
                    ->maxLength(50),
                BlockAppearance::cssClassField(),
            ])]),
        ];
    }

    /**
     * A row's or column's anchor, link, CSS classes and background image layer, every value checked again, for the
     * section view. A row or column with an image or a link is a layer of its own (sb-node-layered), so they sit
     * behind its blocks.
     *
     * @param  array<string, mixed>  $node
     * @param  array{image: ?string, overlayStyle: ?string}  $background
     * @return array{id: ?string, link: ?array{url: string, newTab: bool}, classes: string, image: ?string, overlayStyle: ?string, adminLabel: ?string}
     */
    private static function nodeExtras(array $node, array $background): array
    {
        $link = is_string($node['link_url'] ?? null) && preg_match('/^(https?:\/\/|\/|#)/i', $node['link_url']) === 1
            ? ['url' => $node['link_url'], 'newTab' => ($node['link_new_tab'] ?? false) === true]
            : null;

        return [
            'id' => is_string($node['anchor'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,50}$/', $node['anchor']) === 1 ? $node['anchor'] : null,
            'link' => $link,
            'classes' => trim(implode(' ', array_filter([
                BlockAppearance::cssClasses($node['css_class'] ?? null),
                $background['image'] !== null || $link !== null ? 'sb-node-layered' : '',
                $link !== null ? 'sb-has-link' : '',
            ]))),
            'image' => $background['image'],
            'overlayStyle' => $background['overlayStyle'],
            'adminLabel' => is_string($node['admin_label'] ?? null) && trim($node['admin_label']) !== '' ? Str::limit(trim($node['admin_label']), 60, '') : null,
        ];
    }

    /**
     * A row's settings (the visual builder's Row settings, and each row's ⚙ in the Section's form): Column structure, its
     * columns (each opening its own settings) and Background on the Content tab; Sizing, Spacing, Border and Box shadow
     * on Design. In the Section's form ($inForm), the layout and columns are in the form itself, so the panel has the
     * rest (and keeps the layout, hidden, for the settings that depend on it).
     *
     * @return array<Component>
     */
    public static function rowSettingsPanel(bool $inForm = false): array
    {
        [$layout, $align, $gap, $reverse] = self::rowLayoutFields();
        $grid = fn (array $fields): Grid => Grid::make(['default' => 1, 'sm' => 2])->schema($fields);

        return [...SettingsPanel::schema([
            ...($inForm ? [] : [
                PanelToggle::content('structure', __('Column structure'), [$layout], 1, isOpen: true),
                PanelToggle::content('columns', __('Columns'), [self::columnsView()], 2, isOpen: true),
            ]),
            PanelToggle::content('background', __('Background'), [$grid(BlockAppearance::backgroundFields())], 80),
            ...self::nodeToggles(__('row')),
            PanelToggle::design('sizing', __('Sizing'), [$grid([$align, $gap, $reverse])], 80),
            PanelToggle::design('spacing', __('Spacing'), [self::paddingField(__('Automatic: some space when the row has a border or shadow.'))], 90),
            PanelToggle::design('border', __('Border'), [$grid([...BorderShadow::borderFields(), self::cornersField()])], 95),
            PanelToggle::design('box_shadow', __('Box shadow'), [$grid(BorderShadow::shadowFields())], 100),
            ...BlockDesign::toggles(self::ROW_DESIGN_WITHOUT),
        ]),
            // In the form, the settings that depend on the layout (Sizing) read it here.
            ...($inForm ? [Hidden::make('layout')] : []),
        ];
    }

    /**
     * A column's settings on their own (the visual builder's Column settings): Background, Link and
     * Admin label on the Content tab; Sizing, Spacing, Border, Box shadow and every design option on Design; CSS ID &
     * classes on Advanced. In the Section's form, each column's ⚙ opens it too.
     *
     * @return array<Component>
     */
    public static function columnSettingsPanel(): array
    {
        $grid = fn (array $fields): Grid => Grid::make(['default' => 1, 'sm' => 2])->schema($fields);

        return SettingsPanel::schema([
            PanelToggle::content('background', __('Background'), [$grid(BlockAppearance::backgroundFields())], 80, isOpen: true),
            ...self::nodeToggles(__('column')),
            PanelToggle::design('sizing', __('Sizing'), [self::columnAlignField()], 80),
            PanelToggle::design('spacing', __('Spacing'), [self::paddingField(__('Automatic: some space when the column has a background, border or shadow.'))], 90),
            PanelToggle::design('border', __('Border'), [$grid([...BorderShadow::borderFields(), self::cornersField()])], 95),
            PanelToggle::design('box_shadow', __('Box shadow'), [$grid(BorderShadow::shadowFields())], 100),
            ...BlockDesign::toggles(self::COLUMN_DESIGN_WITHOUT),
        ]);
    }

    /**
     * A row's or column's look with its design options (Design tab: sizing, spacing, borders, filters, transform…)
     * added, as for a block, every target on the row or column itself.
     *
     * @param  array{class: string, style: string}  $look
     * @param  array<string, mixed>  $node
     * @param  array<int, string>  $without  the design options its panel leaves out
     * @return array<string, mixed>
     */
    private static function withDesign(array $look, array $node, array $without): array
    {
        $design = BlockDesign::resolve(BlockDesign::without($node['design'] ?? null, $without));

        if ($design->isEmpty()) {
            return $look;
        }

        $join = fn (string ...$parts): string => trim(implode(' ', array_filter($parts, fn (string $part): bool => trim($part) !== '')));

        return [
            ...$look,
            'class' => $join($look['class'], ...array_map(fn (string $target): string => $design->classes($target), DesignOutput::TARGETS)),
            'style' => $join($look['style'], ...array_map(fn (string $target): string => $design->style($target), DesignOutput::TARGETS)),
        ];
    }

    /**
     * A row's layout, line-up, gap and phone order.
     *
     * @return array{0: Select, 1: Select, 2: Select, 3: Toggle}
     */
    private static function rowLayoutFields(): array
    {
        return [
            Select::make('layout')
                ->label(__('Layout'))
                ->hintIcon(self::HELP_ICON, __('How the row is split into columns, side by side. On phones, the columns sit one under another.'))
                ->options(self::layoutOptions())
                ->default('2')
                ->selectablePlaceholder(false)
                ->live()
                // Fewer columns: a column that no longer fits passes its blocks to the last one kept.
                ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => $set('columns', self::fitColumns(
                    is_array($get('columns')) ? $get('columns') : [],
                    self::columnCount($state),
                    fn (): string => (string) Str::uuid(),
                ))),
            Select::make('align')
                ->label(__('Line up columns at the'))
                ->hintIcon(self::HELP_ICON, __('Where a shorter column sits next to a taller one. Same height stretches the columns to match, so their buttons line up.'))
                ->options(array_map(fn (array $alignment): string => __($alignment[0]), self::ALIGNMENTS))
                ->default('start')
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => self::columnCount($get('layout')) > 1),
            Select::make('gap')
                ->label(__('Space between columns'))
                ->hintIcon(self::HELP_ICON, __('The gap between this row\'s columns, and between blocks in a column.'))
                ->options(array_map(fn (array $gap): string => __($gap[0]), self::ROW_GAPS))
                ->default('section')
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => self::columnCount($get('layout')) > 1),
            Toggle::make('reverse_on_phones')
                ->label(__('Last column first on phones'))
                ->helperText(__('E.g. so an image on the right shows above its text.'))
                ->inline(false)
                ->visible(fn (Get $get): bool => self::columnCount($get('layout')) > 1),
        ];
    }

    private static function columnAlignField(): Select
    {
        return Select::make('align')
            ->label(__('Line up at the'))
            ->hintIcon(self::HELP_ICON, __('Where this column sits next to a taller one. Same as the row follows the row\'s Line up columns at the.'))
            ->options(array_map(fn (array $alignment): string => __($alignment[0]), self::COLUMN_ALIGNMENTS))
            ->default('row')
            ->selectablePlaceholder(false);
    }

    private static function cornersField(): Select
    {
        return Select::make('corners')
            ->label(__('Corners'))
            ->options(array_map(fn (array $corners): string => __($corners[0]), self::ROW_CORNERS))
            ->default('none')
            ->selectablePlaceholder(false);
    }

    private static function paddingField(string $helperText): Select
    {
        return Select::make('padding')
            ->label(__('Space inside'))
            ->options(array_map(fn (array $padding): string => __($padding[0]), self::ROW_PADDINGS))
            ->default('auto')
            ->helperText($helperText)
            ->selectablePlaceholder(false);
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('gap')
                ->label(__('Space between rows and columns'))
                ->hintIcon(self::HELP_ICON, __('The gap between this section\'s rows, and between columns. A row can set its own Space between columns.'))
                ->options(array_map(fn (array $gap): string => __($gap[0]), self::GAPS))
                ->default('medium')
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * The section's rows that have the expected shape: each column's well-formed blocks and the classes and style from
     * its settings, and all the row's blocks in order (`blocks`, for code that only needs to look through a row's
     * blocks). A one-column row's column has its settings too (the section view draws it as one cell when it has any). A column's
     * "textScheme" is null when its text follows the section's. "gap" is the row's own column gap, or null for the
     * section's.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{uid: ?string, layout: string, align: string, gap: ?string, reverse: bool, class: string, style: string, textScheme: ?string, id: ?string, link: ?array{url: string, newTab: bool}, image: ?string, overlayStyle: ?string, adminLabel: ?string, columns: array<int, array{uid: ?string, blocks: array<int, array{type: string, data: array<string, mixed>}>, class: string, style: string, textScheme: ?string, id: ?string, link: ?array{url: string, newTab: bool}, image: ?string, overlayStyle: ?string, adminLabel: ?string}>, blocks: array<int, array{type: string, data: array<string, mixed>}>}>
     */
    public static function rows(array $data): array
    {
        return collect(is_array($data['rows'] ?? null) ? $data['rows'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                $layout = static::pick($row['layout'] ?? null, self::LAYOUTS, 'stacked');
                $columns = array_map(fn (array $column): array => [
                    'uid' => self::uidOf($column),
                    'blocks' => array_values(array_filter(
                        $column['blocks'],
                        fn (mixed $block): bool => is_array($block) && is_string($block['type'] ?? null) && is_array($block['data'] ?? null),
                    )),
                    ...self::withDesign(self::columnLook($column), $column, self::COLUMN_DESIGN_WITHOUT),
                ], array_values(self::normaliseRow($row)['columns']));
                // A row's background (colour or gradient) and its text colour, as a column's.
                $background = BlockAppearance::backgroundLook($row);
                $extras = self::nodeExtras($row, $background);
                $rowLook = self::withDesign([
                    'class' => trim($background['class'].' '.self::rowClass($row, $background['hasBackground']).' '.$extras['classes']),
                    'style' => trim($background['style'].' '.BorderShadow::style($row)),
                ], $row, self::ROW_DESIGN_WITHOUT);

                return [
                    'uid' => self::uidOf($row),
                    'layout' => $layout,
                    'align' => static::pick($row['align'] ?? null, self::ALIGNMENTS, 'start'),
                    'gap' => self::ROW_GAPS[static::pick($row['gap'] ?? null, self::ROW_GAPS, 'section')][1],
                    'reverse' => (bool) ($row['reverse_on_phones'] ?? false),
                    'class' => $rowLook['class'],
                    'style' => $rowLook['style'],
                    'textScheme' => $background['textScheme'],
                    ...array_diff_key($extras, ['classes' => true]),
                    'columns' => $columns,
                    'blocks' => array_merge(...array_column($columns, 'blocks')),
                ];
            })
            ->values()
            ->all();
    }

    public static function columnCount(mixed $layout): int
    {
        return self::COLUMN_COUNTS[static::pick($layout, self::LAYOUTS, 'stacked')];
    }

    /**
     * The Layout choices, grouped by how many columns they have. Numeric keys ("2") are integers, as in LAYOUTS.
     *
     * @return array<string, array<int|string, string>>
     */
    public static function layoutOptions(): array
    {
        $options = [];
        $label = fn (array $layout): string => __($layout[0]);

        foreach (self::LAYOUTS as $key => $layout) {
            $count = self::COLUMN_COUNTS[$key];
            $group = $count >= 4 ? __('Four or five columns') : trans_choice('One column|:count columns', $count, ['count' => $count]);
            $options[$group][$key] = $label($layout);
        }

        return $options;
    }

    /**
     * Where a Builder in a Section's column is, for its block panels' titles: "Row 1 › Column 2" ("Row 1" in a row
     * of one column), or null for a Builder that isn't in a column.
     */
    public static function placeOf(Builder $component): ?string
    {
        $columns = $component->getContainer()->getParentComponent();

        if (! $columns instanceof Repeater || $columns->getName() !== 'columns') {
            return null;
        }

        $rows = $columns->getContainer()->getParentComponent();
        $rowNumber = $rows instanceof Repeater
            ? (int) array_search(Str::afterLast((string) $columns->getContainer()->getStatePath(), '.'), array_keys((array) $rows->getRawState()), true) + 1
            : 1;
        $position = self::columnPosition($component);

        return $position['count'] > 1
            ? __('Row :row › Column :column', ['row' => $rowNumber, 'column' => $position['number']])
            : __('Row :row', ['row' => $rowNumber]);
    }

    /**
     * A stored row with its blocks in `columns`, as many as its layout has. A row saved by 1.4 or earlier kept one
     * `blocks` list that filled the columns in order (wrapping onto a new line): each column gets the blocks that sat
     * in it, so the row looks the same. Keys are kept, so the editor's item IDs survive.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normaliseRow(array $row): array
    {
        $count = self::columnCount($row['layout'] ?? null);

        if (is_array($row['columns'] ?? null)) {
            $columns = array_map(
                fn (array $column): array => [...$column, 'blocks' => is_array($column['blocks'] ?? null) ? $column['blocks'] : []],
                array_filter($row['columns'], 'is_array'),
            );
        } else {
            $columns = array_fill(0, $count, ['blocks' => []]);
            $position = 0;

            foreach (is_array($row['blocks'] ?? null) ? $row['blocks'] : [] as $key => $block) {
                $columns[$position++ % $count]['blocks'][$key] = $block;
            }
        }

        unset($row['blocks']);
        $row['columns'] = self::fitColumns($columns, $count);

        return $row;
    }

    /**
     * Columns cut or padded to $count. A column that no longer fits passes its blocks to the last one kept; new
     * columns are empty, keyed by $newKey (the editor's item IDs) or added to the list.
     *
     * @param  array<int|string, array{blocks: array<int|string, mixed>}>  $columns
     * @param  (Closure(): string)|null  $newKey
     * @return array<int|string, array{blocks: array<int|string, mixed>}>
     */
    public static function fitColumns(array $columns, int $count, ?Closure $newKey = null): array
    {
        $columns = array_map(fn (mixed $column): array => is_array($column) ? [...$column, 'blocks' => is_array($column['blocks'] ?? null) ? $column['blocks'] : []] : ['blocks' => []], $columns);
        $kept = array_slice($columns, 0, $count, preserve_keys: true);
        $lastKey = array_key_last($kept);

        foreach (array_slice($columns, $count, preserve_keys: true) as $extra) {
            $kept[$lastKey]['blocks'] = [...$kept[$lastKey]['blocks'], ...$extra['blocks']];
        }

        while (count($kept) < $count) {
            if ($newKey !== null) {
                $kept[$newKey()] = ['blocks' => []];
            } else {
                $kept[] = ['blocks' => []];
            }
        }

        return $kept;
    }

    /**
     * Stored blocks (a page's, or a library item's) with every Section's rows in today's shape (normaliseRow()).
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public static function normaliseBlocks(array $blocks): array
    {
        return array_map(function (mixed $block): mixed {
            if (! is_array($block) || ($block['type'] ?? null) !== self::NAME || ! is_array($block['data']['rows'] ?? null)) {
                return $block;
            }

            $block['data']['rows'] = array_map(fn (mixed $row): mixed => is_array($row) ? self::normaliseRow($row) : $row, $block['data']['rows']);

            return $block;
        }, $blocks);
    }

    /**
     * For a column's Builder in the editor: which column it is (from 1) and how many the row has.
     *
     * @return array{number: int, count: int, key: string, columns: Repeater}
     */
    private static function columnPosition(Builder $component): array
    {
        /** @var Repeater $columns */
        $columns = $component->getContainer()->getParentComponent();
        $keys = array_keys((array) $columns->getRawState());
        $key = Str::afterLast((string) $component->getContainer()->getStatePath(), '.');

        return ['number' => (int) array_search($key, $keys, true) + 1, 'count' => count($keys), 'key' => $key, 'columns' => $columns];
    }

    /**
     * Move left and Move right on a block card in a column, to the end of the next column that way.
     *
     * @return array<int, Action>
     */
    private static function moveActions(): array
    {
        return array_map(fn (int $direction): Action => Action::make($direction < 0 ? 'moveToPreviousColumn' : 'moveToNextColumn')
            ->label($direction < 0 ? __('Move to the column on the left') : __('Move to the column on the right'))
            ->tooltip($direction < 0 ? __('Move to the column on the left') : __('Move to the column on the right'))
            ->icon($direction < 0 ? Heroicon::ArrowLeft : Heroicon::ArrowRight)
            ->iconButton()
            ->color('gray')
            ->visible(function (Builder $component) use ($direction): bool {
                $position = self::columnPosition($component);

                return $direction < 0 ? $position['number'] > 1 : $position['number'] < $position['count'];
            })
            ->action(function (array $arguments, Builder $component) use ($direction): void {
                $position = self::columnPosition($component);
                $state = (array) $position['columns']->getRawState();
                $keys = array_keys($state);
                $to = $keys[$position['number'] - 1 + $direction] ?? null;
                $block = $state[$position['key']]['blocks'][$arguments['item'] ?? ''] ?? null;

                if ($to === null || $block === null) {
                    return;
                }

                unset($state[$position['key']]['blocks'][$arguments['item']]);
                $state[$to]['blocks'][$arguments['item']] = $block;

                $position['columns']->rawState($state);
                $position['columns']->callAfterStateUpdated();
            }), [-1, 1]);
    }

    /**
     * The row's corners and padding (Row style).
     *
     * @param  array<string, mixed>  $row
     */
    private static function rowClass(array $row, bool $hasBackground = false): string
    {
        $padding = static::pick($row['padding'] ?? null, self::ROW_PADDINGS, 'auto');
        $isFramed = $hasBackground || BorderShadow::style($row) !== '';

        return trim(implode(' ', array_filter([
            self::ROW_CORNERS[static::pick($row['corners'] ?? null, self::ROW_CORNERS, 'none')][1],
            $padding === 'auto' ? ($isFramed ? self::ROW_PADDINGS['medium'][1] : '') : self::ROW_PADDINGS[$padding][1],
        ])));
    }

    /**
     * A side-by-side column's classes and style from its settings: background and text colour, padding (Automatic pads
     * a column with a background, border or shadow), corners, border, shadow and alignment.
     *
     * @param  array<string, mixed>  $column
     * @return array{class: string, style: string, textScheme: ?string, id: ?string, link: ?array{url: string, newTab: bool}, image: ?string, overlayStyle: ?string, adminLabel: ?string}
     */
    private static function columnLook(array $column): array
    {
        $background = BlockAppearance::backgroundLook($column);
        $extras = self::nodeExtras($column, $background);
        $frame = BorderShadow::style($column);
        $padding = static::pick($column['padding'] ?? null, self::ROW_PADDINGS, 'auto');

        return [
            'class' => trim(implode(' ', array_filter([
                $background['class'],
                $padding === 'auto' ? ($background['hasBackground'] || $frame !== '' ? self::ROW_PADDINGS['medium'][1] : '') : self::ROW_PADDINGS[$padding][1],
                self::ROW_CORNERS[static::pick($column['corners'] ?? null, self::ROW_CORNERS, 'none')][1],
                self::COLUMN_ALIGNMENTS[static::pick($column['align'] ?? null, self::COLUMN_ALIGNMENTS, 'row')][1],
                $extras['classes'],
            ]))),
            'style' => trim($background['style'].' '.$frame),
            'textScheme' => $background['textScheme'],
            ...array_diff_key($extras, ['classes' => true]),
        ];
    }

    /**
     * A row's or column's saved uid (BlockSchema), if it's a safe one.
     *
     * @param  array<string, mixed>  $item
     */
    private static function uidOf(array $item): ?string
    {
        return is_string($item['uid'] ?? null) && preg_match(BlockSchema::UID_PATTERN, $item['uid']) === 1 ? $item['uid'] : null;
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
     * "2 equal columns: Text, Image | Image", for a row in the editor (columns split by "|").
     *
     * @param  array<string, mixed>  $state
     */
    public static function rowLabel(array $state): string
    {
        $row = self::rows(['rows' => [$state]])[0] ?? ['layout' => 'stacked', 'columns' => [], 'blocks' => []];
        $registry = app(BlockRegistry::class);
        $columns = array_map(
            fn (array $column): string => $column['blocks'] === [] ? __('empty') : implode(', ', array_map(fn (array $block): string => $registry->labelFor($block['type']), $column['blocks'])),
            $row['columns'],
        );

        return __(self::LAYOUTS[$row['layout']][0]).($row['blocks'] === [] ? '' : ': '.implode(' | ', $columns));
    }
}
