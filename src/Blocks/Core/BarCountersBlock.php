<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Percentages as bars (Divi's Bar Counters module), each with its label and number above it.
 */
class BarCountersBlock extends PercentCountersBlock
{
    /** Bar height => [label, height]. */
    public const HEIGHTS = [
        'thin' => ['Thin', '0.375rem'],
        'medium' => ['Medium', '0.75rem'],
        'thick' => ['Thick', '1.25rem'],
    ];

    public static function name(): string
    {
        return 'bar_counters';
    }

    public static function label(): string
    {
        return __('Bar counters');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedBars3BottomLeft;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    protected static function itemName(): string
    {
        return __('bar');
    }

    protected static function shapeFields(): array
    {
        return [
            Select::make('height')
                ->label(__('Bar height'))
                ->options(array_map(fn (array $height): string => __($height[0]), self::HEIGHTS))
                ->default('medium')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            ...parent::viewData($data, $page),
            'height' => self::HEIGHTS[static::pick($data['height'] ?? null, self::HEIGHTS, 'medium')][1],
        ];
    }
}
