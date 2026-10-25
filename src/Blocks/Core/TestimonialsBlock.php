<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;

class TestimonialsBlock extends PageBlock
{
    public static function name(): string
    {
        return 'testimonials';
    }

    public static function label(): string
    {
        return __('Testimonials');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedChatBubbleLeftRight;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Repeater::make('items')
                ->label(__('Testimonials'))
                ->schema([
                    Textarea::make('quote')->label(__('Quote'))->required()->rows(3)->maxLength(800),
                    Grid::make(2)->schema([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(100),
                        TextInput::make('role')->label(__('Role or company'))->maxLength(150),
                    ]),
                    static::imageUpload('photo', __('Photo (optional)')),
                ])
                ->minItems(1)
                ->defaultItems(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->addActionLabel(__('Add testimonial')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            static::columnsSelect(2, [1, 2, 3]),
        ];
    }
}
