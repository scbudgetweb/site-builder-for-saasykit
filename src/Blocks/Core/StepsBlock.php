<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class StepsBlock extends PageBlock
{
    public static function name(): string
    {
        return 'steps';
    }

    public static function label(): string
    {
        return __('Steps (how it works)');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedListBullet;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('items'), __('step'))
                ->label(__('Steps'))
                ->schema([
                    TextInput::make('title')->label(__('Title'))->required()->maxLength(100),
                    Textarea::make('text')->label(__('Text'))->rows(2)->maxLength(400),
                    static::iconSelect('icon', __('Icon (optional)'))
                        ->helperText(__('Shown instead of the step number.')),
                ])
                ->minItems(2)
                ->maxItems(6)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel(__('Add step')),
        ];
    }
}
