<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Theme Builder: the site's logo from Branding (the header or the footer one), linking to the home page.
 */
class SiteLogoBlock extends PageBlock
{
    public const LOGOS = ['header' => 'The header logo', 'footer' => 'The footer logo'];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public const MIN_HEIGHT = 16;

    public const MAX_HEIGHT = 200;

    public static function name(): string
    {
        return 'site_logo';
    }

    public static function label(): string
    {
        return __('Logo');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSparkles;
    }

    public static function templateTypes(): ?array
    {
        return ['header', 'footer'];
    }

    protected static function fields(): array
    {
        return [
            Select::make('logo')
                ->label(__('Logo'))
                ->hintIcon(self::HELP_ICON, __('The logos are uploaded in Branding › Brand. Without one, the site\'s name shows.'))
                ->options(array_map(fn (string $label): string => __($label), self::LOGOS))
                ->default('header')
                ->selectablePlaceholder(false),
            TextInput::make('height')
                ->label(__('Height'))
                ->hintIcon(self::HELP_ICON, __('How tall the logo is. Its width follows. Blank: the standard 32 px.'))
                ->numeric()
                ->integer()
                ->minValue(self::MIN_HEIGHT)
                ->maxValue(self::MAX_HEIGHT)
                ->suffix('px'),
            Select::make('align')
                ->label(__('Line up'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $height = is_numeric($data['height'] ?? null) ? (int) $data['height'] : null;

        return [
            'place' => static::pick($data['logo'] ?? null, self::LOGOS, 'header'),
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'left'),
            'heightStyle' => $height === null ? '' : 'height: '.max(self::MIN_HEIGHT, min(self::MAX_HEIGHT, $height)).'px; width: auto;',
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
