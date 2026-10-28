<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class FeaturesBlock extends PageBlock
{
    public static function name(): string
    {
        return 'features';
    }

    public static function label(): string
    {
        return __('Features');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSparkles;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('items'), __('feature'))
                ->label(__('Features'))
                ->schema([
                    Grid::make(2)->schema([
                        static::iconSelect(),
                        TextInput::make('title')->label(__('Title'))->required()->maxLength(100),
                    ]),
                    Textarea::make('text')->label(__('Text'))->rows(2)->maxLength(400),
                    Grid::make(2)->schema([
                        TextInput::make('link_url')
                            ->label(__('Link (optional)'))
                            ->hintIcon(self::HELP_ICON, __('A page on this site (/about-us), a part of this page (#pricing) or a full web address (https://…).'))
                            ->maxLength(500)
                            ->regex('/^(https?:\/\/|\/|#)/i')
                            ->validationMessages(['regex' => __('Start the link with https://, / or #.')]),
                        TextInput::make('link_label')->label(__('Link text'))->maxLength(60)->placeholder(__('Find out more')),
                    ]),
                ])
                ->minItems(1)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel(__('Add feature')),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            CardStyle::fieldset(),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            static::columnsSelect(3),
        ];
    }
}
