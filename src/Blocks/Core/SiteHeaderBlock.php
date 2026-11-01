<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Livewire\Component as LivewireComponent;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Filament\Pages\ManageNavigation;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Navigation\NavigationMenus;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Theme Builder: a whole header in one block: the logo, the links from Website › Navigation
 * (with their dropdowns and mega menus), the account links and a call-to-action button, and the phone menu button, laid
 * out by a Style. Edit links changes Navigation's header links where you are. The Logo, Menu and Account links blocks
 * stay for layouts of your own. Not cached: the current page's link is marked, and the account links depend on the
 * visitor.
 */
class SiteHeaderBlock extends PageBlock
{
    public const STYLES = [
        'logo_left' => 'Logo on the left, links on the right',
        'links_centre' => 'Logo on the left, links in the middle',
        'centred_logo' => 'Logo in the middle, links underneath',
        'split' => 'Logo in the middle of the links',
        'menu_only' => 'Logo and a menu button only (the mobile menu at every size)',
    ];

    /** Below which width the links give way to the menu button. */
    public const BREAKPOINTS = ['lg' => 'Below desktop width (tablets and phones)', 'md' => 'Below tablet width (phones only)'];

    /** Height: label and classes. */
    public const HEIGHTS = [
        'compact' => ['Compact', 'min-h-14'],
        'standard' => ['Standard', 'min-h-16'],
        'tall' => ['Tall', 'min-h-20'],
    ];

    /** Content width: label and classes. */
    public const WIDTHS = [
        'contained' => ['Contained (as the page)', 'max-w-7xl'],
        'full' => ['Full width', 'max-w-none'],
    ];

    public const BACKGROUNDS = [
        'header' => 'The header colours from Branding (unless Background is set)',
        'none' => 'See-through (what\'s behind shows)',
    ];

    /** With a Background of its own, the links follow its text colour (light on dark) unless they have their own colours. */
    private const LINKS_ON_OWN_BACKGROUND = [
        'link_color' => ['--color-sb-header-link', 'currentColor'],
        'link_hover_color' => ['--color-sb-header-link-hover', 'color-mix(in oklab, currentColor 75%, transparent)'],
        'account_link' => ['--color-sb-header-account-link', 'currentColor'],
        'account_link_hover' => ['--color-sb-header-account-link-hover', 'color-mix(in oklab, currentColor 75%, transparent)'],
    ];

    public const CTA_FOR = ['guests' => 'Visitors who aren\'t signed in', 'everyone' => 'Everyone'];

    /** Setting => the Branding header token it overrides for this header (as the Menu block's). */
    public const COLOURS = SiteMenuBlock::COLOURS;

    public const MIN_LOGO_HEIGHT = 16;

    public const MAX_LOGO_HEIGHT = 120;

    public static function name(): string
    {
        return 'site_header';
    }

    public static function label(): string
    {
        return __('Header bar');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedWindow;
    }

    public static function templateTypes(): ?array
    {
        return ['header'];
    }

    public static function defaultWidth(): string
    {
        return 'full';
    }

    public static function defaultSpacing(): string
    {
        return 'none';
    }

    /** Its call-to-action button can take a site button style, so Design › Buttons styles it. */
    public static function hasButtons(): bool
    {
        return true;
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            Select::make('style')
                ->label(__('Style'))
                ->options(array_map(fn (string $label): string => __($label), self::STYLES))
                ->default('logo_left')
                ->selectablePlaceholder(false)
                ->live(),
            Fieldset::make(__('Logo'))->schema([
                Toggle::make('show_logo')
                    ->label(__('Show the logo'))
                    ->hintIcon(self::HELP_ICON, __('The header logo from Branding › Brand, linking to the home page. Without one, the site\'s name shows.'))
                    ->default(true)
                    ->live(),
                TextInput::make('logo_height')
                    ->label(__('Logo height'))
                    ->placeholder('32')
                    ->numeric()
                    ->integer()
                    ->minValue(self::MIN_LOGO_HEIGHT)
                    ->maxValue(self::MAX_LOGO_HEIGHT)
                    ->suffix('px')
                    ->visible(fn (Get $get): bool => (bool) ($get('show_logo') ?? true)),
            ]),
            Fieldset::make(__('Links'))->schema([
                Toggle::make('show_links')
                    ->label(__('Show the links'))
                    ->hintIcon(self::HELP_ICON, __('The header links from Website › Navigation, with their dropdowns and mega menus. Edit links changes them for the whole site.'))
                    ->default(true)
                    ->columnSpanFull(),
                Actions::make([self::editLinksAction()])->key('header_links_actions')->columnSpanFull(),
            ]),
            Fieldset::make(__('Account'))->schema([
                Toggle::make('show_account')
                    ->label(__('Show Log in, or the account menu once signed in'))
                    ->default(true)
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('login_label')
                    ->label(__('Log in text'))
                    ->placeholder(__('Log in'))
                    ->maxLength(40)
                    ->visible(fn (Get $get): bool => (bool) ($get('show_account') ?? true)),
            ]),
            Fieldset::make(__('Button'))->schema([
                Toggle::make('show_cta')
                    ->label(__('Show a button'))
                    ->hintIcon(self::HELP_ICON, __('A call to action, such as Book now or Get started, at the end of the header and at the bottom of the mobile menu.'))
                    ->default(true)
                    ->live()
                    ->columnSpanFull(),
                Grid::make(['default' => 1, 'sm' => 2])
                    ->visible(fn (Get $get): bool => (bool) ($get('show_cta') ?? true))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('cta_label')
                            ->label(__('Button text'))
                            ->placeholder(__('Get started'))
                            ->maxLength(40),
                        TextInput::make('cta_url')
                            ->label(__('Button link'))
                            ->hintIcon(self::HELP_ICON, __('Blank: the pricing on the home page. Or a page on this site (/book), a part of a page (#pricing), a full web address, an email (mailto:…) or a phone number (tel:…).'))
                            ->placeholder('/#pricing')
                            ->maxLength(500)
                            ->regex(self::LINK_PATTERN),
                        Select::make('cta_for')
                            ->label(__('Shown to'))
                            ->options(array_map(fn (string $label): string => __($label), self::CTA_FOR))
                            ->default('guests')
                            ->selectablePlaceholder(false),
                        Select::make('cta_style')
                            ->label(__('Button look'))
                            ->options(fn (): array => ['header' => __('The header button from Branding')] + array_map(
                                fn (string $name): string => __('Button style: :name', ['name' => $name]),
                                app(BrandingStyle::class)->buttonNames(),
                            ))
                            ->default('header')
                            ->selectablePlaceholder(false),
                        Toggle::make('cta_new_tab')
                            ->label(__('Open in a new tab'))
                            ->default(false),
                        Toggle::make('cta_on_phones')
                            ->label(__('Keep it beside the menu button on phones'))
                            ->default(false),
                    ]),
            ]),
            Select::make('breakpoint')
                ->label(__('Show the menu button'))
                ->hintIcon(self::HELP_ICON, __('Below this width, the links and account links give way to a menu button that opens the mobile menu (Website › Navigation › Mobile menu).'))
                ->options(array_map(fn (string $label): string => __($label), self::BREAKPOINTS))
                ->default('lg')
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => ($get('style') ?? 'logo_left') !== 'menu_only'),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('height')
                    ->label(__('Height'))
                    ->options(array_map(fn (array $height): string => __($height[0]), self::HEIGHTS))
                    ->default('standard')
                    ->selectablePlaceholder(false),
                Select::make('content_width')
                    ->label(__('Content width'))
                    ->hintIcon(self::HELP_ICON, __('How far the logo and links spread. The background always runs edge to edge.'))
                    ->options(array_map(fn (array $width): string => __($width[0]), self::WIDTHS))
                    ->default('contained')
                    ->selectablePlaceholder(false),
                Select::make('header_background')
                    ->label(__('Header colours'))
                    ->hintIcon(self::HELP_ICON, __('With a Background of its own (in Content), that shows instead, and the links follow its text colour unless they have colours of their own.'))
                    ->options(array_map(fn (string $label): string => __($label), self::BACKGROUNDS))
                    ->default('header')
                    ->selectablePlaceholder(false),
                Toggle::make('border')
                    ->label(__('A line underneath'))
                    ->default(true)
                    ->inline(false),
            ]),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Header colours');
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

    /**
     * Edit links: Website › Navigation's header links in a window, saved for the whole site, and the canvas redrawn.
     */
    private static function editLinksAction(): Action
    {
        return Action::make('editHeaderLinks')
            ->label(__('Edit links'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => ManageNavigation::canAccess())
            ->modalHeading(__('Header links'))
            ->modalDescription(__('The same links as Website › Navigation › Header: they show in every header and mobile menu on the site.'))
            ->modalWidth('3xl')
            ->modalSubmitActionLabel(__('Save links'))
            ->fillForm(fn (NavigationMenus $menus): array => ['header' => $menus->headerItems()])
            ->schema([ManageNavigation::headerLinksRepeater()])
            ->action(function (array $data, NavigationMenus $menus, LivewireComponent $livewire): void {
                abort_unless(ManageNavigation::canAccess(), 403);

                $menus->saveHeader(is_array($data['header'] ?? null) ? $data['header'] : []);

                if (method_exists($livewire, 'refreshCanvas')) {
                    $livewire->refreshCanvas();
                }

                Notification::make()->title(__('Header links saved'))->success()->send();
            });
    }

    public static function viewData(array $data, Page $page): array
    {
        $text = fn (string $key, string $default): string => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : $default;
        $style = static::pick($data['style'] ?? null, self::STYLES, 'logo_left');
        $logoHeight = is_numeric($data['logo_height'] ?? null) ? max(self::MIN_LOGO_HEIGHT, min(self::MAX_LOGO_HEIGHT, (int) $data['logo_height'])) : null;
        $links = ($data['show_links'] ?? true) !== false ? app(NavigationMenus::class)->header() : [];
        $ctaStyle = is_string($data['cta_style'] ?? null) ? $data['cta_style'] : 'header';
        $colourStyle = '';
        // The block's own Background (its Content tab) wins over the Branding header colours.
        $hasOwnBackground = is_array($data['appearance'] ?? null) && is_string($data['appearance']['background'] ?? null) && $data['appearance']['background'] !== 'none';

        if ($hasOwnBackground) {
            foreach (self::LINKS_ON_OWN_BACKGROUND as $key => [$token, $value]) {
                if (Colour::css($data[$key] ?? null) === null) {
                    $colourStyle .= "{$token}: {$value}; ";
                }
            }
        }

        foreach (self::COLOURS as $key => $token) {
            if (($colour = Colour::css($data[$key] ?? null)) !== null) {
                $colourStyle .= "{$token}: {$colour}; ";
            }
        }

        return [
            'headerStyle' => $style,
            'breakpoint' => $style === 'menu_only' ? 'always' : static::pick($data['breakpoint'] ?? null, self::BREAKPOINTS, 'lg'),
            'showsLogo' => ($data['show_logo'] ?? true) !== false,
            'logoStyle' => $logoHeight === null ? null : "height: {$logoHeight}px; width: auto;",
            'links' => $links,
            // "Logo in the middle of the links": the first half on its left, the rest on its right.
            'linkHalves' => array_chunk($links, max(1, (int) ceil(count($links) / 2))),
            'showsAccount' => ($data['show_account'] ?? true) !== false,
            'loginLabel' => $text('login_label', __('Log in')),
            'hasUserMenu' => view()->exists('components.layouts.app.user-menu'),
            'cta' => ($data['show_cta'] ?? true) !== false && (($data['cta_for'] ?? 'guests') === 'everyone' || ! auth()->check()) ? [
                'label' => $text('cta_label', __('Get started')),
                'url' => is_string($data['cta_url'] ?? null) && preg_match(self::LINK_PATTERN, $data['cta_url']) === 1 ? $data['cta_url'] : route('home').'#pricing',
                'newTab' => (bool) ($data['cta_new_tab'] ?? false),
                'classes' => in_array((int) $ctaStyle, BrandingStyle::BUTTON_SLOTS, true)
                    ? 'site-button site-button-'.(int) $ctaStyle.' px-4 py-2 font-semibold'
                    : 'rounded-sb-button bg-sb-header-button px-4 py-2 font-semibold text-sb-header-button-text transition-colors hover:bg-sb-header-button-hover hover:text-sb-header-button-text-hover focus-visible:bg-sb-header-button-hover focus-visible:text-sb-header-button-text-hover',
                'onPhones' => (bool) ($data['cta_on_phones'] ?? false),
            ] : null,
            'heightClasses' => self::HEIGHTS[static::pick($data['height'] ?? null, self::HEIGHTS, 'standard')][1],
            'widthClasses' => self::WIDTHS[static::pick($data['content_width'] ?? null, self::WIDTHS, 'contained')][1],
            'isOverlayable' => ! $hasOwnBackground,
            'hasHeaderBackground' => ! $hasOwnBackground && static::pick($data['header_background'] ?? null, self::BACKGROUNDS, 'header') === 'header',
            'hasBorder' => ($data['border'] ?? true) !== false,
            'colourStyle' => trim($colourStyle),
            'hasOwnMobileColours' => Colour::css($data['mobile_background'] ?? null) !== null,
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
