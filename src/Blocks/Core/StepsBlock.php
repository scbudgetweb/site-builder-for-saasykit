<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Support\Colour;

class StepsBlock extends PageBlock
{
    /** Connector line between the steps' markers => label (a CSS border style). */
    public const CONNECTORS = ['solid' => 'Solid line', 'dashed' => 'Dashed line', 'dotted' => 'Dotted line'];

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
                    Toggle::make('highlight')
                        ->label(__('Highlight this step'))
                        ->hintIcon(self::HELP_ICON, __('Marks the step, e.g. where the visitor is now: its marker is ringed and a little larger.')),
                ])
                ->minItems(2)
                ->maxItems(6)
                ->defaultItems(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel(__('Add step')),
        ];
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Steps'))
            ->icon(__('Step numbers'))
            ->text('title', __('Title text'))
            ->text('text', __('Step text'));
    }

    protected static function styleToggleTitle(): string
    {
        return __('Connectors');
    }

    protected static function styleFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('connector')
                    ->label(__('Line between steps'))
                    ->hintIcon(self::HELP_ICON, __('Joins the step numbers across each row, on tablets and computers.'))
                    ->options(array_map(fn (string $label): string => __($label), self::CONNECTORS))
                    ->placeholder(__('None'))
                    ->live(),
                SiteColour::make('connector_colour')
                    ->label(__('Line colour'))
                    ->helperText(__('Blank: a faint line in the text colour.'))
                    ->visible(fn (Get $get): bool => filled($get('connector'))),
            ]),
        ];
    }

    /**
     * The connector's class and style for the list of steps, from its checked settings: ['', ''] with none.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    public static function connector(array $data): array
    {
        $style = static::pick($data['connector'] ?? null, ['none' => true, ...self::CONNECTORS], 'none');

        if ($style === 'none') {
            return ['', ''];
        }

        $colour = Colour::css($data['connector_colour'] ?? null);

        return ['sb-steps-line', "--sb-steps-ls: {$style};".($colour !== null ? " --sb-steps-lc: {$colour};" : '')];
    }
}
