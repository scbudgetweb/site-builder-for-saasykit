<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Forms\HeadingInput;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * A heading on its own, with an optional small heading: its level (H1–H6) is for search
 * engines and screen readers, and sets its standard size. Design › Heading text styles it, by level too.
 */
class HeadingBlock extends PageBlock
{
    /** Level => label. */
    public const LEVELS = [
        'h1' => 'H1: the page\'s main heading (one per page)',
        'h2' => 'H2: a section heading',
        'h3' => 'H3',
        'h4' => 'H4',
        'h5' => 'H5',
        'h6' => 'H6',
    ];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public static function name(): string
    {
        return 'heading';
    }

    public static function label(): string
    {
        return __('Heading');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedH1;
    }

    /** A heading usually leads into the block after it, so less space than a block with content. */
    public static function defaultSpacing(): string
    {
        return 'small';
    }

    /** Its alignment is its own (Layout), so only the decoration and size. */
    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    /** A heading only: no body text. */
    public static function hasBodyText(): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            static::eyebrowFields(),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                HeadingInput::make('heading')
                    ->label(__('Heading'))
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->columnSpan(['sm' => 2]),
                Select::make('level')
                    ->label(__('Level'))
                    ->hintIcon(self::HELP_ICON, __('What the heading is in the page\'s outline, for search engines and screen readers. It also sets its standard size; Heading size and Design › Heading text change how it looks.'))
                    ->options(array_map(fn (string $label): string => __($label), self::LEVELS))
                    ->default('h2')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'heading' => is_string($data['heading'] ?? null) ? $data['heading'] : '',
            'level' => static::pick($data['level'] ?? null, self::LEVELS, 'h2'),
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'left'),
        ];
    }
}
