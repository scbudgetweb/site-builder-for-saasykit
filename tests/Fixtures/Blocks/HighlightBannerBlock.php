<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\Core\BannerBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * The example host subclass of a core block (developer guide, "Extending a core block"): the Banner with an optional
 * highlight below or beside its text. Registered with SiteBuilder::blocks(), it replaces the core Banner by name.
 */
class HighlightBannerBlock extends BannerBlock
{
    protected static function fields(): array
    {
        $showsHighlight = fn (Get $get): bool => (bool) $get('show_highlight');

        return [
            ...parent::fields(),
            Toggle::make('show_highlight')->label(__('Show a highlight'))->live(),
            TextInput::make('highlight')
                ->label(__('Highlight'))
                ->maxLength(100)
                ->required($showsHighlight)
                ->visible($showsHighlight),
            Select::make('highlight_position')
                ->label(__('Highlight position'))
                ->options(array_map(fn (string $label): string => __($label), self::EXTRA_CONTENT_POSITIONS))
                ->default('below')
                ->selectablePlaceholder(false)
                ->visible($showsHighlight),
        ];
    }

    /**
     * Every saved value is re-checked before it reaches the view (lesson 11).
     */
    public static function viewData(array $data, Page $page): array
    {
        return [
            ...parent::viewData($data, $page),
            'highlight' => is_string($data['highlight'] ?? null) ? $data['highlight'] : '',
        ];
    }

    protected static function extraContentView(array $data): ?string
    {
        return ($data['show_highlight'] ?? false) === true ? 'blocks.banner-highlight' : null;
    }

    protected static function extraContentPosition(array $data): string
    {
        return static::pick($data['highlight_position'] ?? null, self::EXTRA_CONTENT_POSITIONS, 'below');
    }
}
