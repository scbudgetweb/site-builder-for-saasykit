<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class DividerBlock extends PageBlock
{
    public static function name(): string
    {
        return 'divider';
    }

    public static function label(): string
    {
        return __('Divider / spacer');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedMinus;
    }

    public static function hasAppearance(): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            Select::make('style')
                ->label(__('Style'))
                ->options(['line' => __('Line'), 'space' => __('Empty space')])
                ->default('line')
                ->selectablePlaceholder(false),
            Select::make('size')
                ->label(__('Space'))
                ->hintIcon(self::HELP_ICON, __('How much room the divider takes up above and below.'))
                ->options(['small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large')])
                ->default('medium')
                ->selectablePlaceholder(false),
            TextInput::make('label')->label(__('Label on the line (optional)'))->maxLength(80),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
