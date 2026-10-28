<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class TeamBlock extends PageBlock
{
    public static function name(): string
    {
        return 'team';
    }

    public static function label(): string
    {
        return __('Team');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedUserGroup;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('members'), __('person'))
                ->label(__('People'))
                ->schema([
                    static::imageUpload('photo', __('Photo')),
                    Grid::make(2)->schema([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(100),
                        TextInput::make('role')->label(__('Role'))->maxLength(150),
                    ]),
                    Textarea::make('bio')->label(__('Short bio'))->rows(3)->maxLength(600),
                    TextInput::make('linkedin')
                        ->label(__('LinkedIn profile'))
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://www.linkedin.com/in/...'),
                ])
                ->minItems(1)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->addActionLabel(__('Add person')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            static::columnsSelect(3),
        ];
    }
}
