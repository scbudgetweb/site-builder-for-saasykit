<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * A title that opens and closes its content (Divi's Toggle module): one FAQ item on its own, open or closed at first.
 * Drawn as <details>, so it works without JavaScript and from the keyboard.
 */
class ToggleBlock extends PageBlock
{
    /** Marker => label. */
    public const MARKERS = [
        'plus' => 'Plus (turns into a cross)',
        'chevron' => 'Arrow (turns over)',
        'none' => 'None',
    ];

    public static function name(): string
    {
        return 'toggle';
    }

    public static function label(): string
    {
        return __('Toggle');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedChevronUpDown;
    }

    public static function defaultWidth(): string
    {
        return 'narrow';
    }

    /** Toggles usually come in a run, one under another. */
    public static function defaultSpacing(): string
    {
        return 'small';
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Toggle'))
            ->text('title', __('Title text'))
            ->text('text', __('Content text'))
            ->withoutBox();
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('title')
                ->label(__('Title'))
                ->required()
                ->maxLength(250)
                ->live(onBlur: true),
            static::richText('content', __('Content'))->required(),
            Toggle::make('open')
                ->label(__('Open at first'))
                ->helperText(__('Visitors can still close it.')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('marker')
                    ->label(__('Open and close marker'))
                    ->options(array_map(fn (string $label): string => __($label), self::MARKERS))
                    ->default('plus')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        $name = parent::itemLabel($state);
        $title = $state['title'] ?? null;

        // An admin label wins, then the title.
        return $name === static::label() && is_string($title) && filled($title) ? static::label().': '.Str::limit($title, 60) : $name;
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'html' => static::html($data['content'] ?? null),
            'isOpen' => ($data['open'] ?? false) === true,
            'marker' => static::pick($data['marker'] ?? null, self::MARKERS, 'plus'),
        ];
    }
}
