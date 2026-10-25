<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * The example host block (developer guide): an app's own block, registered with SiteBuilder::blocks(). It gets the
 * shared tabs and style settings like any core block, and renders from the app's own view.
 */
class OpeningHoursBlock extends PageBlock
{
    public static function name(): string
    {
        return 'opening_hours';
    }

    public static function label(): string
    {
        return __('Opening hours');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Repeater::make('hours')
                ->label(__('Hours'))
                ->schema([
                    TextInput::make('day')->label(__('Day'))->required()->maxLength(30),
                    TextInput::make('times')->label(__('Times'))->required()->maxLength(60),
                ])
                ->minItems(1)
                ->columns(2),
        ];
    }

    public static function view(): string
    {
        return 'blocks.opening-hours';
    }

    /**
     * Every saved value is re-checked before it reaches the view (lesson 11).
     */
    public static function viewData(array $data, Page $page): array
    {
        return [
            'hours' => collect(is_array($data['hours'] ?? null) ? $data['hours'] : [])
                ->filter(fn (mixed $row): bool => is_array($row) && is_string($row['day'] ?? null) && is_string($row['times'] ?? null))
                ->values()
                ->all(),
        ];
    }
}
