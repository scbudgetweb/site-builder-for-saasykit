<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\IconLibrary;

/**
 * One icon on its own (Divi's Icon module). Design › Icon styles it as the icons in Features do (a tile, its shape,
 * size and colours, with hover); Content › Link makes it a link.
 */
class IconBlock extends PageBlock
{
    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public static function name(): string
    {
        return 'icon';
    }

    public static function label(): string
    {
        return __('Icon');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedStar;
    }

    /** An icon usually sits with the blocks around it, so less space than a block with content. */
    public static function defaultSpacing(): string
    {
        return 'small';
    }

    /** Without an icon from the library there's nothing to show, so no empty space either. */
    public static function isVisible(array $data): bool
    {
        return is_string($data['icon'] ?? null) && $data['icon'] !== '' && IconLibrary::isValid($data['icon']);
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Icon'))->icon(__('Icon'))->withoutBox();
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                static::iconSelect()->required(),
                TextInput::make('label')
                    ->label(__('What it shows (optional)'))
                    ->hintIcon(self::HELP_ICON, __('Read out by screen readers, e.g. "Wheelchair access". Leave it blank when the icon is only decoration, or the text beside it says the same.'))
                    ->maxLength(100),
            ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('center')
                ->selectablePlaceholder(false),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        $name = parent::itemLabel($state);
        $label = $state['label'] ?? null;

        // An admin label wins, then what the icon shows.
        return $name === static::label() && is_string($label) && filled($label) ? static::label().': '.$label : $name;
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'icon' => is_string($data['icon'] ?? null) ? $data['icon'] : null,
            'label' => is_string($data['label'] ?? null) ? trim($data['label']) : '',
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'center'),
        ];
    }
}
