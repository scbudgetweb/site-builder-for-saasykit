<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Percentages as rings, with the number inside and the label under each.
 */
class CircleCountersBlock extends PercentCountersBlock
{
    protected const MAX_ITEMS = 8;

    /** Size => [label, width]. */
    public const SIZES = [
        'small' => ['Small', '7rem'],
        'medium' => ['Medium', '9rem'],
        'large' => ['Large', '11rem'],
    ];

    /** Ring thickness => [label, stroke width out of the ring's 100]. */
    public const THICKNESSES = [
        'thin' => ['Thin', 6],
        'medium' => ['Medium', 10],
        'thick' => ['Thick', 16],
    ];

    public static function name(): string
    {
        return 'circle_counters';
    }

    public static function label(): string
    {
        return __('Circle counters');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedChartPie;
    }

    protected static function itemName(): string
    {
        return __('circle');
    }

    protected static function shapeFields(): array
    {
        return [
            Select::make('size')
                ->label(__('Size'))
                ->options(array_map(fn (array $size): string => __($size[0]), self::SIZES))
                ->default('medium')
                ->selectablePlaceholder(false),
            Select::make('thickness')
                ->label(__('Ring thickness'))
                ->options(array_map(fn (array $thickness): string => __($thickness[0]), self::THICKNESSES))
                ->default('medium')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $stroke = self::THICKNESSES[static::pick($data['thickness'] ?? null, self::THICKNESSES, 'medium')][1];

        return [
            ...parent::viewData($data, $page),
            'size' => self::SIZES[static::pick($data['size'] ?? null, self::SIZES, 'medium')][1],
            'stroke' => $stroke,
            'radius' => 50 - $stroke / 2,
        ];
    }
}
