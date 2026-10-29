<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;

/**
 * Theme Builder (footers): the footer's link columns from Website › Navigation › Footer, side by side.
 */
class SiteFooterLinksBlock extends PageBlock
{
    /** Columns on wide screens => grid classes (one under another on phones). */
    public const GRIDS = [
        1 => '',
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-3',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
    ];

    public static function name(): string
    {
        return 'site_footer_links';
    }

    public static function label(): string
    {
        return __('Footer links');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedQueueList;
    }

    public static function templateTypes(): ?array
    {
        return ['footer'];
    }

    /** Its links is body text (.block-text) of its own, not rich text. */
    public static function hasBodyText(): bool
    {
        return true;
    }

    protected static function fields(): array
    {
        return [
            Text::make(__('The link columns are set in Website › Navigation › Footer.')),
            Select::make('columns')
                ->label(__('Columns on wide screens'))
                ->hintIcon(self::HELP_ICON, __('Blank: one for each link column. On phones they sit one under another.'))
                ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
                ->placeholder(__('One per link column')),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $columns = app(NavigationMenus::class)->footer()['columns'];
        $count = is_numeric($data['columns'] ?? null) ? (int) $data['columns'] : count($columns);

        return [
            'columns' => $columns,
            'grid' => self::GRIDS[max(1, min(4, $count))],
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
