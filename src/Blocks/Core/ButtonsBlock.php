<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class ButtonsBlock extends PageBlock
{
    public static function name(): string
    {
        return 'buttons';
    }

    public static function label(): string
    {
        return __('Buttons');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedCursorArrowRays;
    }

    protected static function fields(): array
    {
        return [
            static::buttonsRepeater(3)->minItems(1)->defaultItems(1),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(['left' => __('Left'), 'center' => __('Centre'), 'right' => __('Right')])
                ->default('center')
                ->selectablePlaceholder(false),
        ];
    }
}
