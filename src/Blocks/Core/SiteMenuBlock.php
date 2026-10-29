<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Theme Builder: the header menu from Website › Navigation (with its dropdowns and mega menus), and below a screen
 * width a menu button that opens the mobile menu, as the standard header does (they share its partials). Not cached:
 * the link for the page being shown is marked as current.
 */
class SiteMenuBlock extends PageBlock
{
    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    /** Below which width the links give way to the menu button. */
    public const BREAKPOINTS = ['lg' => 'Below desktop width (tablets and phones)', 'md' => 'Below tablet width (phones only)'];

    /** Setting => the Branding header token it overrides for this menu. */
    public const COLOURS = [
        'link_color' => '--color-sb-header-link',
        'link_hover_color' => '--color-sb-header-link-hover',
        'mobile_background' => '--color-sb-header-bg',
        'mobile_text' => '--color-sb-header-text',
    ];

    public static function name(): string
    {
        return 'site_menu';
    }

    public static function label(): string
    {
        return __('Menu');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedBars3;
    }

    public static function templateTypes(): ?array
    {
        return ['header', 'footer'];
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            Text::make(__('The links are set in Website › Navigation, with their dropdowns, mega menus and the mobile menu.')),
            Select::make('align')
                ->label(__('Line up the links'))
                ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                ->default('right')
                ->selectablePlaceholder(false),
            Select::make('breakpoint')
                ->label(__('Show the menu button'))
                ->hintIcon(self::HELP_ICON, __('Below this width, the links are replaced by a menu button that opens the mobile menu.'))
                ->options(array_map(fn (string $label): string => __($label), self::BREAKPOINTS))
                ->default('lg')
                ->selectablePlaceholder(false),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Menu');
    }

    protected static function styleFields(): array
    {
        return [
            SiteColour::make('link_color')
                ->label(__('Link colour'))
                ->hintIcon(self::HELP_ICON, __('Blank: the header colours from Branding › Header & footer.')),
            SiteColour::make('link_hover_color')
                ->label(__('Link colour when pointed at')),
            SiteColour::make('mobile_background')
                ->label(__('Mobile menu background')),
            SiteColour::make('mobile_text')
                ->label(__('Mobile menu text')),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $style = '';

        foreach (self::COLOURS as $key => $token) {
            if (($colour = Colour::css($data[$key] ?? null)) !== null) {
                $style .= "{$token}: {$colour}; ";
            }
        }

        return [
            'align' => static::pick($data['align'] ?? null, self::ALIGNMENTS, 'right'),
            'breakpoint' => static::pick($data['breakpoint'] ?? null, self::BREAKPOINTS, 'lg'),
            'colourStyle' => trim($style),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
