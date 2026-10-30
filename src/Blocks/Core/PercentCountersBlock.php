<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Percentages drawn as meters (Circle counters, Bar counters): each a label and 0–100, in the block's colour or its own,
 * filling up as it scrolls into view. The HTML holds the full value, so it reads right without JavaScript.
 */
abstract class PercentCountersBlock extends PageBlock
{
    /** The most meters in one block. */
    protected const MAX_ITEMS = 12;

    /** What the panel calls one meter, e.g. "circle". */
    abstract protected static function itemName(): string;

    /**
     * The block's own Style settings, beside the colours.
     *
     * @return array<int, mixed>
     */
    abstract protected static function shapeFields(): array;

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(static::label())
            ->text('value', __('Number text'))
            ->text('label', __('Label text'));
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('items'), static::itemName())
                ->label(static::label())
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 3])->schema([
                        TextInput::make('label')->label(__('Label'))->required()->maxLength(80)->columnSpan(['sm' => 2]),
                        TextInput::make('value')
                            ->label(__('Percentage'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                        SiteColour::make('colour')
                            ->label(__('Colour (optional)'))
                            ->helperText(__('Blank: the block\'s colour (Style).')),
                    ]),
                ])
                ->minItems(1)
                ->maxItems(static::MAX_ITEMS)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->addActionLabel(__('Add :item', ['item' => static::itemName()])),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Toggle::make('animate')->label(__('Fill up when scrolled into view'))->default(true),
                Toggle::make('show_percent')->label(__('Show the % sign'))->default(true),
            ]),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                SiteColour::make('meter_colour')
                    ->label(__('Colour'))
                    ->helperText(__('Blank: the site colour.')),
                SiteColour::make('track_colour')
                    ->label(__('Track colour'))
                    ->helperText(__('The unfilled part. Blank: a light tint of the text colour.')),
                ...static::shapeFields(),
            ]),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'items' => collect(is_array($data['items'] ?? null) ? $data['items'] : [])
                ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['label'] ?? null) && filled($item['label']) && is_numeric($item['value'] ?? null))
                ->map(fn (array $item): array => [
                    'label' => $item['label'],
                    'value' => (int) max(0, min(100, round((float) $item['value']))),
                    'colour' => Colour::css($item['colour'] ?? null),
                ])
                ->values()
                ->all(),
            'animate' => ($data['animate'] ?? true) !== false,
            'percentSign' => ($data['show_percent'] ?? true) !== false ? '%' : '',
            // Blank: the site colour, a lighter shade on a dark background (the view knows which: $isDark).
            'meterColour' => Colour::css($data['meter_colour'] ?? null),
            'trackColour' => Colour::css($data['track_colour'] ?? null) ?? 'color-mix(in oklab, currentColor 12%, transparent)',
        ];
    }

    /**
     * The meter's Alpine data: $value shown at once without animation (or with reduced motion), otherwise filled from
     * 0 when it comes into view. Inline, so a block added in the visual builder works without a script.
     */
    public static function alpine(int $value, bool $animate): string
    {
        return $animate
            ? "{ target: {$value}, shown: {$value}, fill() { if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { this.shown = this.target; return; } const start = performance.now(); const step = (now) => { const progress = Math.min((now - start) / 1200, 1); this.shown = Math.round(this.target * (1 - Math.pow(1 - progress, 3))); if (progress < 1) requestAnimationFrame(step); }; requestAnimationFrame(step); } }"
            : "{ shown: {$value} }";
    }
}
