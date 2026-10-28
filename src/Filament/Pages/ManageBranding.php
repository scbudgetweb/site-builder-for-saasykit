<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;
use ScBudgetWeb\SiteBuilder\Branding\BrandingSection;
use ScBudgetWeb\SiteBuilder\Branding\BrandingStyle;
use ScBudgetWeb\SiteBuilder\Branding\ColourGroup;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\Colour;
use ScBudgetWeb\SiteBuilder\Support\CustomCss;
use ScBudgetWeb\SiteBuilder\Support\SafeSvg;

/**
 * Admin › Website › Branding: the site's identity (logos, icons, colours, fonts, text sizes, buttons, shapes,
 * header and footer, banners, dashboards and custom CSS). Every setting is optional.
 *
 * @property-read Schema $form
 */
class ManageBranding extends Page
{
    use RestrictsFileUploadsToSchemaComponents;

    public const LOGO_FILE_TYPES = ['image/png', 'image/svg+xml', 'image/webp'];

    public const FAVICON_FILE_TYPES = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];

    public const APPLE_TOUCH_ICON_FILE_TYPES = ['image/png'];

    public const BANNER_IMAGE_FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const SHARE_IMAGE_FILE_TYPES = ['image/jpeg', 'image/png'];

    /**
     * Header and footer colour fields => [area, label], for the form and the low-contrast warning.
     *
     * @var array<string, array{string, string}>
     */
    private const ELEMENT_COLOR_FIELDS = [
        'header_bg_color' => ['Header', 'Background colour'],
        'header_text_color' => ['Header', 'Site name & menu icon colour'],
        'header_link_color' => ['Header', 'Menu link colour'],
        'header_link_hover_color' => ['Header', 'Menu link hover colour'],
        'header_menu_bg_color' => ['Header', 'Dropdown background'],
        'header_menu_link_color' => ['Header', 'Dropdown link colour'],
        'header_menu_link_hover_color' => ['Header', 'Dropdown link hover colour'],
        'header_menu_heading_color' => ['Header', 'Mega menu heading & description colour'],
        'header_button_color' => ['Header', 'Button colour'],
        'header_button_hover_color' => ['Header', 'Button hover colour'],
        'header_button_text_color' => ['Header', 'Button text colour'],
        'header_button_text_hover_color' => ['Header', 'Button hover text colour'],
        'footer_bg_color' => ['Footer', 'Background colour'],
        'footer_text_color' => ['Footer', 'Text & heading colour'],
        'footer_link_color' => ['Footer', 'Link colour'],
        'footer_link_hover_color' => ['Footer', 'Link hover colour'],
        'footer_social_color' => ['Footer', 'Social icon colour'],
        'footer_social_hover_color' => ['Footer', 'Social icon hover colour'],
        'announcement_bg_color' => ['Announcement bar', 'Background colour'],
        'announcement_text_color' => ['Announcement bar', 'Text & close button colour'],
        'announcement_link_color' => ['Announcement bar', 'Link colour'],
        'announcement_link_hover_color' => ['Announcement bar', 'Link hover colour'],
    ];

    /**
     * Banner => [section title, description].
     *
     * @var array<string, array{string, string}>
     */
    private const BANNER_SECTIONS = [
        'hero' => ['Hero', 'The banner at the top of the home page.'],
        'inner' => ['Inner pages', 'The banner at the top of other pages, such as the blog, Privacy Policy and Terms of Service.'],
    ];

    private const UPLOAD_FIELDS = ['logo_light', 'logo_dark', 'favicon', 'apple_touch_icon', 'share_image', 'hero_image', 'inner_image'];

    protected string $view = 'site-builder::filament.pages.manage-branding';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'branding';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return app(SiteBuilder::class)->navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('Branding');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Branding');
    }

    public static function canAccess(): bool
    {
        $permission = config('site-builder.permissions.branding', 'update settings');

        return is_string($permission) && (bool) auth()->user()?->can($permission);
    }

    public function mount(Branding $branding): void
    {
        $state = [];

        foreach ($this->fields() as $field => $key) {
            $state[$field] = $branding->get($key);
        }

        $state['logo_in_panels'] = $branding->showsLogoInPanels();

        foreach (Branding::LOGO_PLACES as $key => [, $defaultVariant]) {
            $state[Str::after($key, 'branding.')] ??= $defaultVariant;
        }

        foreach ($branding->banners() as $banner) {
            $state["{$banner}_background_type"] ??= Branding::BANNER_TYPE_GRADIENT;
            $state["{$banner}_gradient_direction"] ??= Colour::DEFAULT_GRADIENT_DIRECTION;
            $state["{$banner}_image_position"] ??= 'center';
            $state["{$banner}_overlay_enabled"] = $branding->isOn(Branding::bannerKey($banner, 'overlay_enabled'));
            $state["{$banner}_overlay_opacity"] = Colour::overlayOpacity($state["{$banner}_overlay_opacity"] ?? null);
            $state["{$banner}_overlay_type"] ??= 'solid';
            $state["{$banner}_overlay_direction"] ??= 'to_bottom';
            $state["{$banner}_overlay_end_opacity"] = is_numeric($state["{$banner}_overlay_end_opacity"] ?? null)
                ? max(0, min(100, (int) $state["{$banner}_overlay_end_opacity"]))
                : 0;
        }

        // Choices open on the standard look, and the button styles with their standard names.
        $state['text_size'] ??= '16';
        $state['heading_scale'] ??= 'standard';
        $state['style_button_corners'] ??= 'rounded';
        $state['style_card_corners'] ??= 'medium';
        $state['style_image_corners'] ??= 'medium';
        $state['style_input_corners'] ??= 'match';
        $state['style_shadows'] ??= 'soft';
        $state['style_heading_weight'] ??= '700';
        $state['style_heading_decoration'] ??= 'none';
        $state['button_on_light'] ??= BrandingStyle::DEFAULT_BUTTON_ON_LIGHT;
        $state['button_on_dark'] ??= BrandingStyle::DEFAULT_BUTTON_ON_DARK;

        foreach (BrandingStyle::BUTTON_SLOTS as $slot) {
            $state["button_{$slot}_name"] ??= BrandingStyle::defaultButtonName($slot);
            $state["button_{$slot}_uppercase"] = $branding->isOn(BrandingStyle::buttonKey($slot, 'uppercase'));
            $state["button_{$slot}_lift"] = $branding->isOn(BrandingStyle::buttonKey($slot, 'lift'));
        }

        // The app's own sections open on their checked values; choices on their defaults. Text defaults are placeholders.
        foreach ($branding->appFields() as $field) {
            // A banner's settings were filled with the hero and inner banners'.
            if ($field->type === BrandingField::BANNER) {
                continue;
            }

            $state[self::appFieldName($field)] = in_array($field->type, [BrandingField::TOGGLE, BrandingField::SELECT, BrandingField::BUTTON_STYLE], true)
                ? $branding->appValue($field->key)
                : $field->clean($branding->get($field->settingKey()));
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $branding = app(Branding::class);
        $fonts = $branding->availableFonts();

        return $schema
            ->components([
                Form::make([
                    // The whole site's look first (brand, type, buttons, shapes), then each area of it. The open tab is kept in the URL.
                    Tabs::make()
                        ->persistTabInQueryString('branding-tab')
                        ->tabs([
                            Tab::make(__('Brand'))
                                ->icon('heroicon-o-sparkles')
                                ->schema([
                                    Section::make(__('Logo & icons'))
                                        ->columns(2)
                                        ->schema([
                                            $this->uploadField('logo_light', __('Logo for light backgrounds'), self::LOGO_FILE_TYPES, 1024)
                                                ->helperText(__('A dark-coloured logo. PNG, SVG or WebP, up to 1 MB. A wide logo around 200 × 50 pixels works best.')),
                                            $this->uploadField('logo_dark', __('Logo for dark backgrounds'), self::LOGO_FILE_TYPES, 1024)
                                                ->helperText(__('A light-coloured (e.g. white) logo. Dashboard dark mode shows whichever logo the dashboards don\'t use.')),
                                            Fieldset::make(__('Which logo to show where'))
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->schema([
                                                    ...collect(Branding::LOGO_PLACES)
                                                        ->map(fn (array $place, string $key): Select => Select::make(Str::after($key, 'branding.'))
                                                            ->label(__($place[0]))
                                                            ->options(['light' => __('Logo for light backgrounds'), 'dark' => __('Logo for dark backgrounds')])
                                                            ->in(['light', 'dark'])
                                                            ->selectablePlaceholder(false)
                                                            ->helperText(match ($key) {
                                                                Branding::LOGO_HEADER, Branding::LOGO_FOOTER => __('Match the background colour set in Header & footer. If that logo isn\'t uploaded, the site name shows instead.'),
                                                                Branding::LOGO_AUTH => __('Sign-in, registration and password pages, and emails, which have white backgrounds.'),
                                                                default => __('The admin panel and the dashboards. Dark mode shows the other logo.'),
                                                            })
                                                            ->visible(fn (Get $get): bool => $key !== Branding::LOGO_DASHBOARDS || (bool) $get('logo_in_panels')))
                                                        ->values()
                                                        ->all(),
                                                    Toggle::make('logo_in_panels')
                                                        ->label(__('Show the logo in the dashboards'))
                                                        ->helperText(__('When off, the dashboards show the site name instead.'))
                                                        ->live()
                                                        ->columnSpanFull(),
                                                ]),
                                            $this->uploadField('favicon', __('Favicon'), self::FAVICON_FILE_TYPES, 256)
                                                ->hintIcon(PageBlock::HELP_ICON, __('Also shown in bookmarks and browser history. Your logo\'s symbol, without words, works best.'))
                                                ->helperText(__('The small icon in the browser tab. ICO, PNG or SVG, up to 256 KB. Square, at least 48 × 48 pixels.')),
                                            $this->uploadField('apple_touch_icon', __('Apple touch icon'), self::APPLE_TOUCH_ICON_FILE_TYPES, 256)
                                                ->helperText(__('The icon shown when someone adds the site to an iPhone or iPad home screen. PNG, 180 × 180 pixels, with no transparency.')),
                                            $this->uploadField('share_image', __('Share image'), self::SHARE_IMAGE_FILE_TYPES, 2048)
                                                ->helperText(__('Shown when a page is shared on social media, unless the page has its own. JPG or PNG, 1200 × 630 pixels, up to 2 MB.')),
                                        ]),
                                    Section::make(__('Colours'))
                                        ->description(__('Your brand colours. Lighter and darker shades (tints) are made from each one automatically, so you only pick one. Leave a colour blank to keep the standard one.'))
                                        ->columns(2)
                                        ->schema([
                                            $this->colorField('site_color', __('Site colour'))
                                                ->hintIcon(PageBlock::HELP_ICON, __('Your main brand colour. Pages use lighter and darker shades of it, e.g. for a "Site colour, light tint" background.'))
                                                ->live()
                                                ->helperText(fn (?string $state): string => $this->siteColorHelperText($state)),
                                            $this->colorField('site_secondary_color', __('Site secondary colour'))
                                                ->hintIcon(PageBlock::HELP_ICON, __('An optional second brand colour for small touches, such as icons and highlights. Leave it blank if your brand has one main colour.'))
                                                ->helperText(__('Used for highlights and accents on the public site.')),
                                        ]),
                                ]),
                            Tab::make(__('Typography'))
                                ->icon('heroicon-o-language')
                                ->schema([
                                    Section::make(__('Website'))
                                        ->description(__('Fonts are stored on this site, never loaded from Google or other font services. New fonts are added by a developer.'))
                                        ->columns(2)
                                        ->schema([
                                            $this->fontSelect('site_heading_font', __('Heading font'), $fonts)
                                                ->placeholder(__('Same as the body text'))
                                                ->helperText(__('Page titles, banner headings and section headings.')),
                                            $this->fontSelect('site_font', __('Body text font'), $fonts)
                                                ->placeholder(__(':font (standard)', ['font' => $branding->fontFamily(Branding::DEFAULT_SITE_FONT)])),
                                            Select::make('text_size')
                                                ->label(__('Body text size'))
                                                ->options(Colour::translated(Branding::TEXT_SIZE_OPTIONS))
                                                ->in(array_keys(Branding::TEXT_SIZE_OPTIONS))
                                                ->selectablePlaceholder(false)
                                                ->helperText(__('The size of ordinary text on the website. Larger text is easier to read for many people.')),
                                            Select::make('heading_scale')
                                                ->label(__('Heading sizes'))
                                                ->hintIcon(PageBlock::HELP_ICON, __('Changes every heading on the website at once. A single block can still choose its own heading size on its Style tab.'))
                                                ->options(BrandingStyle::labels(Branding::HEADING_SCALE_OPTIONS))
                                                ->in(array_keys(Branding::HEADING_SCALE_OPTIONS))
                                                ->selectablePlaceholder(false)
                                                ->helperText(__('Makes the website\'s page and section headings a little smaller or larger.')),
                                        ]),
                                    $this->headingsSection(),
                                    Section::make(__('Text colours'))
                                        ->description(__('Most sites keep the standard near-black and white. Blocks use dark text on light backgrounds and light text on dark ones; change them here to tint your text. Leave blank to keep the standard.'))
                                        ->collapsible()
                                        ->collapsed()
                                        ->schema([
                                            $this->textColors(__('Dark text (on light backgrounds)'), ['text_dark_heading_color' => __('Headings'), 'text_dark_body_color' => __('Body text')], '#ffffff'),
                                            $this->textColors(__('Light text (on dark backgrounds)'), ['text_light_heading_color' => __('Headings'), 'text_light_body_color' => __('Body text')], '#111827'),
                                        ]),
                                    Section::make(__('Dashboards'))
                                        ->description(__('Fonts for the admin panel and the dashboards, not the public website. Leave blank to keep each one\'s standard font.'))
                                        ->collapsible()
                                        ->collapsed()
                                        ->columns(2)
                                        ->schema([
                                            $this->fontSelect('app_heading_font', __('Heading font'), $fonts)
                                                ->placeholder(__('Same as the body text'))
                                                ->helperText(__('Page titles and section headings.')),
                                            $this->fontSelect('app_font', __('Body text font'), $fonts)
                                                ->placeholder(__('Standard')),
                                        ]),
                                ]),
                            Tab::make(__('Buttons'))
                                ->icon('heroicon-o-cursor-arrow-rays')
                                ->schema([$this->buttonStylesSection()]),
                            Tab::make(__('Corners & shadows'))
                                ->icon('heroicon-o-swatch')
                                ->schema([$this->shapesSection()]),
                            Tab::make(__('Header & footer'))
                                ->icon('heroicon-o-window')
                                ->schema([
                                    Section::make(__('Header'))
                                        ->description(__('Colours for the site header and the mobile menu. Hover colours are also used when a link or button is pressed or reached with the keyboard. Leave blank to keep the standard look.'))
                                        ->schema([
                                            $this->colorPairs(__('Background & text'), ['header_bg_color', 'header_text_color']),
                                            $this->colorPairs(__('Menu links'), ['header_link_color', 'header_link_hover_color']),
                                            $this->moreOptions(__('Dropdown menus and the header button.'), [
                                                $this->colorPairs(__('Dropdowns & mega menus'), ['header_menu_bg_color', 'header_menu_link_color', 'header_menu_link_hover_color', 'header_menu_heading_color']),
                                                $this->colorPairs(__('Button'), ['header_button_color', 'header_button_hover_color', 'header_button_text_color', 'header_button_text_hover_color']),
                                            ]),
                                        ]),
                                    Section::make(__('Footer'))
                                        ->description(__('Colours for the site footer. Social icons only show for networks with a link in General Settings. Leave blank to keep the standard look.'))
                                        ->schema([
                                            $this->colorPairs(__('Background & text'), ['footer_bg_color', 'footer_text_color']),
                                            $this->colorPairs(__('Links'), ['footer_link_color', 'footer_link_hover_color']),
                                            $this->colorPairs(__('Social icons'), ['footer_social_color', 'footer_social_hover_color']),
                                        ]),
                                    Section::make(__('Announcement bar'))
                                        ->description(__('Colours for announcements (Admin › Announcements), on the website and in the dashboard. Leave blank to keep the standard look.'))
                                        ->schema([
                                            $this->colorPairs(__('Background & text'), ['announcement_bg_color', 'announcement_text_color']),
                                            $this->colorPairs(__('Links'), ['announcement_link_color', 'announcement_link_hover_color']),
                                        ]),
                                    // The app's own element colours (SiteBuilder::brandingColours()), a section per group.
                                    ...array_values(array_map(fn (ColourGroup $group): Section => $this->appColourSection($group), $branding->appColourGroups())),
                                ]),
                            Tab::make(__('Banners'))
                                ->icon('heroicon-o-photo')
                                ->schema(array_map(fn (string $banner): Section => $this->bannerSection($banner), Branding::BANNERS)),
                            Tab::make(__('Dashboards'))
                                ->icon('heroicon-o-squares-2x2')
                                ->schema([
                                    Section::make(__('Dashboards'))
                                        ->description(__('The main colour of each dashboard. Leave blank to keep its standard colour. Giving the admin panel a colour of its own makes it obvious when you\'re in admin. Dashboard fonts are on the Typography tab, and the dashboard logo on the Brand tab.'))
                                        ->columns(2)
                                        ->schema(array_map(
                                            fn (string $panelId): ColorPicker => $this->colorField(Str::after(Branding::panelColorKey($panelId), 'branding.'), __(':panel colour', ['panel' => Str::headline($panelId)])),
                                            $this->panelIds(),
                                        )),
                                ]),
                            Tab::make(__('Custom CSS'))
                                ->icon('heroicon-o-code-bracket')
                                ->schema([
                                    Section::make(__('Custom CSS'))
                                        ->description(__('For people who write CSS (the code that styles web pages). If you don\'t, leave this empty: everything else in Branding works without it. Your CSS is added last, so it overrides the rest of the site\'s look. Check the site after saving, because CSS can break the layout. Mistakes such as a misspelt property or an extra "}" are listed below after saving.'))
                                        ->schema([
                                            CodeEditor::make('custom_css')
                                                ->label(__('Your CSS'))
                                                ->hintIcon(PageBlock::HELP_ICON, __('For safety, "<", @import and files from other websites aren\'t allowed. url(), image-set() and src() can point to files on this site (starting with /), inline images or #references. To style one block, give it a name in its CSS classes (Advanced tab) and use that here, e.g. .promo { … }.'))
                                                ->language(Language::Css)
                                                // Mistakes that don't make it unsafe are saved, but listed here.
                                                ->helperText(fn (?string $state): ?HtmlString => ($warnings = CustomCss::warnings($state)) === []
                                                    ? null
                                                    : new HtmlString(implode('<br>', array_map(fn (string $warning): string => e($warning), [__('Check your CSS:'), ...$warnings]))))
                                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                                    if (($problem = CustomCss::problem(is_string($value) ? $value : null)) !== null) {
                                                        $fail($problem);
                                                    }
                                                }),
                                        ]),
                                ]),
                            // The app's own sections (SiteBuilder::brandingSection()), a tab each, after the package's.
                            ...array_values(array_map(fn (BrandingSection $section): Tab => $this->appSectionTab($section), $branding->appSections())),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save changes'))
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(Branding $branding): void
    {
        $data = $this->form->getState();

        $previousUploads = [];

        foreach ($this->uploadFields() as $field) {
            $previousUploads[$field] = $branding->string($this->fields()[$field]);
        }

        $values = [];

        foreach ($this->fields() as $field => $key) {
            // Fields hidden by the form (e.g. image settings while a banner is a gradient) aren't in the
            // state, so their saved values are kept for when the admin switches back.
            if (array_key_exists($field, $data)) {
                $values[$key] = $data[$field];
            }
        }

        // Toggles: store "off" as no value, so the standard look applies.
        $values[Branding::LOGO_IN_PANELS] = ($data['logo_in_panels'] ?? false) ? true : null;

        foreach (BrandingStyle::BUTTON_SLOTS as $slot) {
            foreach (['uppercase', 'lift'] as $toggle) {
                $values[BrandingStyle::buttonKey($slot, $toggle)] = ($data["button_{$slot}_{$toggle}"] ?? false) ? true : null;
            }
        }

        // The app's own toggles keep "off" (false), because their default may be "on".
        foreach ($branding->appFields() as $field) {
            if ($field->type === BrandingField::TOGGLE && array_key_exists(self::appFieldName($field), $data)) {
                $values[$field->settingKey()] = (bool) $data[self::appFieldName($field)];
            }
        }

        foreach ($branding->banners() as $banner) {
            if (array_key_exists("{$banner}_overlay_enabled", $data)) {
                $values[Branding::bannerKey($banner, 'overlay_enabled')] = $data["{$banner}_overlay_enabled"] ? true : null;
            }
        }

        $branding->save($values);

        $this->deleteReplacedUploads($branding, $previousUploads, $data);

        $this->warnAboutUnreadableColours($branding, $data);

        if (($cssWarnings = CustomCss::warnings(is_string($data['custom_css'] ?? null) ? $data['custom_css'] : null)) !== []) {
            Notification::make()
                ->title(__('Custom CSS saved, but check it'))
                ->body(new HtmlString(implode('<br>', array_map(fn (string $warning): string => e($warning), $cssWarnings))))
                ->warning()
                ->persistent()
                ->send();
        }

        Notification::make()
            ->title(__('Branding saved'))
            ->body(__('Reload the page to see the new branding.'))
            ->success()
            ->send();
    }

    /**
     * Form field => settings key. Field names are the keys without the "branding." prefix.
     *
     * @return array<string, string>
     */
    private function fields(): array
    {
        $keys = [
            ...Branding::keys(),
            ...app(Branding::class)->appColourKeys(),
            ...app(Branding::class)->appSectionKeys(),
            ...array_map(fn (string $panelId): string => Branding::panelColorKey($panelId), $this->panelIds()),
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [Str::after($key, 'branding.') => $key])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function panelIds(): array
    {
        return array_keys(Filament::getPanels());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function warnAboutUnreadableColours(Branding $branding, array $data): void
    {
        $unreadable = [
            ...$this->unreadableElementColors($data),
            ...$this->unreadableBannerText($branding, $data),
            ...array_map(fn (string $name): string => __('Button styles').' › '.$name, app(BrandingStyle::class)->unreadableButtonStyles($data)),
        ];

        if ($unreadable !== []) {
            Notification::make()
                ->title(__('Some colours may be hard to read'))
                ->body(__('These colours have low contrast against their background: :fields.', ['fields' => implode(', ', $unreadable)]))
                ->warning()
                ->persistent()
                ->send();
        }

        foreach ($branding->banners() as $banner) {
            if ($branding->bannerImageNeedsOverlay($this->bannerState($banner, $data))) {
                Notification::make()
                    ->title(__(':banner banner text may be hard to read', ['banner' => $this->bannerTitle($banner)]))
                    ->body(__('Text can be hard to read on a busy image. Turn on the overlay at :opacity% or more.', ['opacity' => Colour::MIN_READABLE_OVERLAY_OPACITY]))
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        if (Colour::isValidHex($data['site_color'] ?? null) && ! Colour::hasAccessibleButtonContrast($data['site_color'])) {
            Notification::make()
                ->title(__('Low contrast site colour'))
                ->body(__('White text on buttons in this colour may be hard to read. Consider a darker shade.'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * Colours as a titled group, normal and hover colours side by side.
     *
     * @param  array<int, string>  $names  ELEMENT_COLOR_FIELDS keys
     */
    private function colorPairs(string $title, array $names): Fieldset
    {
        return Fieldset::make($title)
            ->columns(2)
            ->schema(array_map(function (string $name): ColorPicker {
                $backgroundField = $this->contrastBackgroundField($name);

                return $this->colorField($name, __(self::ELEMENT_COLOR_FIELDS[$name][1]))
                    ->hintIcon(str_contains($name, 'hover') ? PageBlock::HELP_ICON : null, str_contains($name, 'hover') ? __('Hover: when someone points at it with the mouse, taps it, or reaches it with the Tab key.') : null)
                    ->live()
                    ->helperText(fn (Get $get, ?string $state): ?string => $backgroundField !== null && $this->isUnreadable($state, $get($backgroundField))
                        ? __('Warning: this may be hard to read on its background colour.')
                        : null);
            }, $names));
    }

    /**
     * An app's own element colours (SiteBuilder::brandingColours()), with the same contrast warning as the built-in ones.
     */
    private function appColourSection(ColourGroup $group): Section
    {
        return Section::make(__($group->title))
            ->description(__('Leave blank to keep the standard look.'))
            ->columns(2)
            ->schema(array_values(array_map(function (string $key) use ($group): ColorPicker {
                $name = Str::after(ColourGroup::settingKey($key), 'branding.');
                $backgroundField = $this->contrastBackgroundField($name);

                return $this->colorField($name, __($group->colours[$key]['label']))
                    ->live()
                    ->helperText(fn (Get $get, ?string $state): ?string => $backgroundField !== null && $this->isUnreadable($state, $get($backgroundField))
                        ? __('Warning: this may be hard to read on its background colour.')
                        : null);
            }, array_keys($group->colours))));
    }

    /**
     * An app's own Branding section (SiteBuilder::brandingSection()) as a tab: its settings, then a section per banner.
     */
    private function appSectionTab(BrandingSection $section): Tab
    {
        $fields = array_filter($section->fields, fn (BrandingField $field): bool => $field->type !== BrandingField::BANNER);
        $banners = array_filter($section->fields, fn (BrandingField $field): bool => $field->type === BrandingField::BANNER);

        return Tab::make(__($section->title))
            ->icon($section->icon ?? 'heroicon-o-puzzle-piece')
            ->schema([
                ...($fields === [] ? [] : [
                    Section::make(__($section->title))
                        ->description($section->description === null ? null : __($section->description))
                        ->columns(2)
                        ->schema(array_values(array_map(fn (BrandingField $field): Component => $this->appFieldComponent($field), $fields))),
                ]),
                ...array_values(array_map(fn (BrandingField $field): Section => $this->bannerSection($field->bannerName()), $banners)),
            ]);
    }

    /**
     * One setting in an app's own section, with the same checks as the package's own: lengths, listed choices, hex
     * colours (with the contrast warning), the Branding button styles and image uploads.
     */
    private function appFieldComponent(BrandingField $field): Component
    {
        $name = self::appFieldName($field);
        $label = __($field->label);
        $default = $field->getDefault();
        $options = fn (): array => array_map(fn (string $option): string => __($option), $field->getOptions());

        $component = match ($field->type) {
            BrandingField::TEXT => TextInput::make($name)->label($label)->maxLength($field->getMaxLength())
                ->placeholder(is_string($default) ? __($default) : null),
            BrandingField::TEXTAREA => Textarea::make($name)->label($label)->rows(3)->maxLength($field->getMaxLength())
                ->placeholder(is_string($default) ? __($default) : null)
                ->columnSpanFull(),
            BrandingField::TOGGLE => Toggle::make($name)->label($label)->columnSpanFull(),
            BrandingField::SELECT => Select::make($name)->label($label)->options($options)->in(array_keys($field->getOptions()))
                ->selectablePlaceholder($default === null),
            BrandingField::COLOUR => $this->colorField($name, $label)
                ->live()
                ->helperText(function (Get $get, ?string $state) use ($name, $field): ?string {
                    $backgroundField = $this->contrastBackgroundField($name);

                    return $backgroundField !== null && $this->isUnreadable($state, $get($backgroundField))
                        ? __('Warning: this may be hard to read on its background colour.')
                        : $field->getHelperText();
                }),
            BrandingField::BUTTON_STYLE => Select::make($name)->label($label)
                ->options(fn (): array => [BrandingField::AUTOMATIC_BUTTON_STYLE => __('Automatic (suits the background)')] + app(BrandingStyle::class)->buttonNames())
                ->in([BrandingField::AUTOMATIC_BUTTON_STYLE, ...array_map('strval', BrandingStyle::BUTTON_SLOTS)])
                ->selectablePlaceholder(false),
            BrandingField::IMAGE => $this->uploadField($name, $label, $field->getFileTypes(), $field->getMaxSize())->image(),
        };

        if ($field->getTooltip() !== null && method_exists($component, 'hintIcon')) {
            $component->hintIcon(PageBlock::HELP_ICON, $field->getTooltip());
        }

        // A colour's helper text is set above, with its contrast warning.
        return $field->type === BrandingField::COLOUR || $field->getHelperText() === null ? $component : $component->helperText($field->getHelperText());
    }

    private static function appFieldName(BrandingField $field): string
    {
        return Str::after($field->settingKey(), 'branding.');
    }

    /**
     * The hero and inner banners' section titles, or an app banner's label.
     */
    private function bannerTitle(string $banner): string
    {
        if (isset(self::BANNER_SECTIONS[$banner])) {
            return __(self::BANNER_SECTIONS[$banner][0]);
        }

        $field = collect(app(Branding::class)->appFields())->first(fn (BrandingField $field): bool => $field->type === BrandingField::BANNER && $field->bannerName() === $banner);

        return $field ? __($field->label) : Str::headline($banner);
    }

    /**
     * Every upload field: the package's, and the app's images and banner images.
     *
     * @return array<int, string>
     */
    private function uploadFields(): array
    {
        $appUploads = [];

        foreach (app(Branding::class)->appFields() as $field) {
            match ($field->type) {
                BrandingField::IMAGE => $appUploads[] = self::appFieldName($field),
                BrandingField::BANNER => $appUploads[] = "{$field->bannerName()}_image",
                default => null,
            };
        }

        return [...self::UPLOAD_FIELDS, ...$appUploads];
    }

    /**
     * @param  array<string, string>  $fonts
     */
    private function fontSelect(string $name, string $label, array $fonts): Select
    {
        return Select::make($name)
            ->label($label)
            ->options($fonts)
            ->in(array_keys($fonts));
    }

    private function bannerSection(string $banner): Section
    {
        $branding = app(Branding::class);
        $title = $this->bannerTitle($banner);
        $description = isset(self::BANNER_SECTIONS[$banner])
            ? __(self::BANNER_SECTIONS[$banner][1])
            : (collect($branding->appFields())->first(fn (BrandingField $field): bool => $field->type === BrandingField::BANNER && $field->bannerName() === $banner)?->getHelperText() ?? '');

        $isImage = fn (Get $get): bool => $get("{$banner}_background_type") === Branding::BANNER_TYPE_IMAGE;
        $isColor = fn (Get $get): bool => $get("{$banner}_background_type") === Branding::BANNER_TYPE_COLOR;
        $isGradient = fn (Get $get): bool => ! $isImage($get) && ! $isColor($get);
        $textWarning = fn (Get $get, string $text): ?string => in_array($text, $branding->unreadableBannerText($this->bannerState($banner, $get)), true)
            ? __('Warning: this may be hard to read on the banner background.')
            : null;
        $overlayWarning = fn (Get $get): ?string => $branding->bannerImageNeedsOverlay($this->bannerState($banner, $get))
            ? __('Warning: text can be hard to read on a busy image. Use an overlay of :opacity% or more (for a gradient, at its stronger end).', ['opacity' => Colour::MIN_READABLE_OVERLAY_OPACITY])
            : null;

        return Section::make($title)
            ->description(trim($description.' '.__('Leave blank to keep the standard look.')))
            ->columns(2)
            ->collapsible()
            ->schema([
                ToggleButtons::make("{$banner}_background_type")
                    ->label(__('Background'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Gradient: two or three colours blending into each other. Colour: one flat colour. Image: a photo, with an optional see-through overlay to keep the text readable.'))
                    ->options([
                        Branding::BANNER_TYPE_GRADIENT => __('Gradient'),
                        Branding::BANNER_TYPE_COLOR => __('Colour'),
                        Branding::BANNER_TYPE_IMAGE => __('Image'),
                    ])
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                $this->colorField("{$banner}_background_color", __('Background colour'))
                    ->required($isColor)
                    ->live()
                    ->visible($isColor),

                $this->colorField("{$banner}_gradient_start", __('Gradient start colour'))
                    ->requiredWith("{$banner}_gradient_end")
                    ->live()
                    ->visible($isGradient),
                $this->colorField("{$banner}_gradient_end", __('Gradient end colour'))
                    ->requiredWith("{$banner}_gradient_start")
                    ->live()
                    ->visible($isGradient),
                $this->colorField("{$banner}_gradient_middle", __('Gradient middle colour (optional)'))
                    ->live()
                    ->visible($isGradient),
                Select::make("{$banner}_gradient_direction")
                    ->label(__('Gradient direction'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Which way the colours blend, from the start colour to the end colour.'))
                    ->options(Colour::translated(Colour::GRADIENT_DIRECTIONS))
                    ->in(array_map('strval', array_keys(Colour::GRADIENT_DIRECTIONS)))
                    ->selectablePlaceholder(false)
                    ->visible($isGradient),

                $this->uploadField("{$banner}_image", __('Background image'), self::BANNER_IMAGE_FILE_TYPES, 2048)
                    ->live()
                    ->helperText(__('JPG, PNG or WebP, up to 2 MB. A wide image around 1920 × 600 pixels works best.'))
                    ->visible($isImage),
                Select::make("{$banner}_image_position")
                    ->label(__('Keep in view when cropped'))
                    ->options(Colour::translated(Colour::IMAGE_POSITIONS))
                    ->in(array_keys(Colour::IMAGE_POSITIONS))
                    ->selectablePlaceholder(false)
                    ->helperText(__('On narrow screens the image is cropped. Choose which part stays visible.'))
                    ->visible($isImage),
                Toggle::make("{$banner}_overlay_enabled")
                    ->label(__('Add a colour overlay'))
                    ->live()
                    ->helperText($overlayWarning)
                    ->visible($isImage)
                    ->columnSpanFull(),
                // One colour or a gradient, like a block's image background (the warning above follows the strengths).
                ...BlockAppearance::overlayFields(fn (Get $get): bool => $isImage($get) && (bool) $get("{$banner}_overlay_enabled"), "{$banner}_", live: true),

                $this->colorField("{$banner}_heading_color", __('Heading colour'))
                    ->live()
                    ->helperText(fn (Get $get): ?string => $textWarning($get, 'heading')),
                $this->colorField("{$banner}_subtitle_color", __('Subtitle colour'))
                    ->live()
                    ->helperText(fn (Get $get): ?string => $textWarning($get, 'subtitle')),
            ]);
    }

    /**
     * One banner's fields from the form (a Get utility) or saved form data, keyed by field name.
     *
     * @param  Get|array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function bannerState(string $banner, Get|array $source): array
    {
        $state = [];

        foreach (Branding::BANNER_FIELDS as $field) {
            $state[$field] = $source instanceof Get ? $source("{$banner}_{$field}") : ($source["{$banner}_{$field}"] ?? null);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function unreadableBannerText(Branding $branding, array $data): array
    {
        $unreadable = [];

        foreach ($branding->banners() as $banner) {
            foreach ($branding->unreadableBannerText($this->bannerState($banner, $data)) as $text) {
                $unreadable[] = $this->bannerTitle($banner).' › '.__($text === 'heading' ? 'Heading colour' : 'Subtitle colour');
            }
        }

        return $unreadable;
    }

    private function contrastBackgroundField(string $field): ?string
    {
        $backgroundKey = app(Branding::class)->contrastPairs()[$this->fields()[$field] ?? ''] ?? null;

        return $backgroundKey ? Str::after($backgroundKey, 'branding.') : null;
    }

    private function isUnreadable(?string $textColor, ?string $backgroundColor): bool
    {
        return Colour::isValidHex($textColor)
            && Colour::isValidHex($backgroundColor)
            && ! Colour::isReadableTextOn($textColor, $backgroundColor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function unreadableElementColors(array $data): array
    {
        $unreadable = [];

        foreach (self::ELEMENT_COLOR_FIELDS as $name => [$area, $label]) {
            $backgroundField = $this->contrastBackgroundField($name);

            if ($backgroundField !== null && $this->isUnreadable($data[$name] ?? null, $data[$backgroundField] ?? null)) {
                $unreadable[] = __($area).' › '.__($label);
            }
        }

        foreach (app(Branding::class)->appColourGroups() as $group) {
            foreach ($group->colours as $key => $colour) {
                $name = Str::after(ColourGroup::settingKey($key), 'branding.');
                $backgroundField = $this->contrastBackgroundField($name);

                if ($backgroundField !== null && $this->isUnreadable($data[$name] ?? null, $data[$backgroundField] ?? null)) {
                    $unreadable[] = __($group->title).' › '.__($colour['label']);
                }
            }
        }

        foreach (app(Branding::class)->appSections() as $section) {
            foreach ($section->fields as $field) {
                $name = self::appFieldName($field);
                $backgroundField = $field->type === BrandingField::COLOUR ? $this->contrastBackgroundField($name) : null;

                if ($backgroundField !== null && $this->isUnreadable($data[$name] ?? null, $data[$backgroundField] ?? null)) {
                    $unreadable[] = __($section->title).' › '.__($field->label);
                }
            }
        }

        return $unreadable;
    }

    /**
     * Section headings (block headings and the theme's section titles): weight and decoration.
     */
    private function headingsSection(): Section
    {
        return Section::make(__('Section headings'))
            ->description(__('Headings that introduce a part of a page, such as a block\'s heading.'))
            ->columns(2)
            ->schema([
                $this->styleSelect('style_heading_weight', __('Weight'), BrandingStyle::HEADING_WEIGHT_OPTIONS)
                    ->hintIcon(PageBlock::HELP_ICON, __('How thick and bold the section headings look.')),
                Select::make('style_heading_decoration')
                    ->label(__('Decoration'))
                    ->options(Colour::translated(BrandingStyle::HEADING_DECORATION_OPTIONS))
                    ->in(array_keys(BrandingStyle::HEADING_DECORATION_OPTIONS))
                    ->selectablePlaceholder(false)
                    ->live()
                    ->helperText(__('A line or bar with each section heading. It follows the heading\'s alignment, and each block can change it on its Style tab.')),
                $this->colorField('style_heading_decoration_color', __('Decoration colour'))
                    ->helperText(__('On light backgrounds. Leave blank to use the site colour.'))
                    ->visible(fn (Get $get): bool => $get('style_heading_decoration') !== 'none'),
                $this->colorField('style_heading_decoration_dark_color', __('Decoration colour on dark backgrounds'))
                    ->helperText(__('In blocks with a dark background. Leave blank to match the heading text (usually white).'))
                    ->visible(fn (Get $get): bool => $get('style_heading_decoration') !== 'none'),
            ]);
    }

    private function shapesSection(): Section
    {
        return Section::make(__('Corners & shadows'))
            ->description(__('Shapes across the public site: the pages you build, cards and forms. The standard choices keep the current look. Button corners are on the Buttons tab.'))
            ->columns(2)
            ->schema([
                $this->styleSelect('style_card_corners', __('Card and panel corners'), BrandingStyle::CARD_CORNER_OPTIONS)
                    ->hintIcon(PageBlock::HELP_ICON, __('How round the corners are on cards (e.g. in Features, Team or Image cards blocks) and on blocks shown as a panel. Square: sharp corners.')),
                $this->styleSelect('style_image_corners', __('Image corners'), BrandingStyle::IMAGE_CORNER_OPTIONS)
                    ->helperText(__('Images in the pages you build. Each Image and Image + text block can override it.')),
                $this->styleSelect('style_input_corners', __('Form field corners'), BrandingStyle::INPUT_CORNER_OPTIONS)
                    ->hintIcon(PageBlock::HELP_ICON, __('The boxes people type into, such as on the sign-in and contact forms.')),
                $this->styleSelect('style_shadows', __('Card shadows'), BrandingStyle::SHADOW_OPTIONS)
                    ->hintIcon(PageBlock::HELP_ICON, __('The soft shadow under cards and panels that lifts them off the page.')),
            ]);
    }

    private function buttonStylesSection(): Section
    {
        $slotOptions = fn (Get $get): array => collect(BrandingStyle::BUTTON_SLOTS)
            ->mapWithKeys(fn (int $slot): array => [(string) $slot => filled($get("button_{$slot}_name")) ? $get("button_{$slot}_name") : BrandingStyle::defaultButtonName($slot)])
            ->all();

        return Section::make(__('Button styles'))
            ->description(__('Four button styles to choose from when adding buttons to a page; the site\'s own buttons use the first one. Leave a colour blank to keep the standard one. Hover colours are also used when a button is pressed or reached with the keyboard.'))
            ->schema([
                $this->styleSelect('style_button_corners', __('Button corners'), BrandingStyle::BUTTON_CORNER_OPTIONS)
                    ->hintIcon(PageBlock::HELP_ICON, __('How round the button corners are. Pill: fully rounded ends.'))
                    ->helperText(__('For every button style.')),
                Tabs::make()->tabs(array_map(fn (int $slot): Tab => $this->buttonStyleTab($slot), BrandingStyle::BUTTON_SLOTS)),
                Section::make(__('Automatic buttons'))
                    ->description(__('A button set to "Automatic" picks one of these by itself, depending on the background it sits on, so it\'s always easy to see.'))
                    ->columns(2)
                    ->compact()
                    ->schema([
                        Select::make('button_on_light')
                            ->label(__('On light backgrounds'))
                            ->options($slotOptions)
                            ->in(array_map('strval', BrandingStyle::BUTTON_SLOTS))
                            ->selectablePlaceholder(false),
                        Select::make('button_on_dark')
                            ->label(__('On dark backgrounds'))
                            ->options($slotOptions)
                            ->in(array_map('strval', BrandingStyle::BUTTON_SLOTS))
                            ->selectablePlaceholder(false),
                    ]),
            ]);
    }

    private function buttonStyleTab(int $slot): Tab
    {
        $unreadableWarning = fn (string $textField, string $backgroundField): Closure => fn (Get $get): ?string => in_array(
            filled($get("button_{$slot}_name")) ? $get("button_{$slot}_name") : BrandingStyle::defaultButtonName($slot),
            app(BrandingStyle::class)->unreadableButtonStyles([
                "button_{$slot}_name" => $get("button_{$slot}_name"),
                "button_{$slot}_{$textField}" => $get("button_{$slot}_{$textField}"),
                "button_{$slot}_{$backgroundField}" => $get("button_{$slot}_{$backgroundField}"),
            ]),
            true,
        ) ? __('Warning: this may be hard to read on its background colour.') : null;

        return Tab::make(fn (Get $get): string => filled($get("button_{$slot}_name")) ? (string) $get("button_{$slot}_name") : BrandingStyle::defaultButtonName($slot))
            ->columns(2)
            ->schema([
                TextInput::make("button_{$slot}_name")
                    ->label(__('Name'))
                    ->helperText(__('Shown when choosing a button style for a page.'))
                    ->required()
                    ->maxLength(30)
                    ->live(onBlur: true)
                    ->columnSpanFull(),
                $this->colorField("button_{$slot}_bg", __('Background colour'))->live(),
                $this->colorField("button_{$slot}_bg_hover", __('Background hover colour'))->hintIcon(PageBlock::HELP_ICON, __('Hover: when someone points at it with the mouse, taps it, or reaches it with the Tab key.'))->live(),
                $this->colorField("button_{$slot}_text", __('Text colour'))
                    ->live()
                    ->helperText($unreadableWarning('text', 'bg')),
                $this->colorField("button_{$slot}_text_hover", __('Text hover colour'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Hover: when someone points at it with the mouse, taps it, or reaches it with the Tab key.'))
                    ->live()
                    ->helperText($unreadableWarning('text_hover', 'bg_hover')),
                $this->colorField("button_{$slot}_border", __('Border colour'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A line around the button. Leave it blank for no border, or set it with no background colour for an outline button.')),
                $this->colorField("button_{$slot}_border_hover", __('Border hover colour')),
                Toggle::make("button_{$slot}_uppercase")
                    ->label(__('Capital letters'))
                    ->helperText(__('Upper-case text with wider letter spacing.')),
                Toggle::make("button_{$slot}_lift")
                    ->label(__('Lift on hover'))
                    ->helperText(__('The button moves up slightly when hovered.')),
            ]);
    }

    /**
     * @param  array<string, array{string, string}>  $options
     */
    private function styleSelect(string $name, string $label, array $options): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(BrandingStyle::labels($options))
            ->in(array_keys($options))
            ->selectablePlaceholder(false);
    }

    /**
     * Settings most sites leave alone, in a collapsed "More options" so the everyday ones stand out.
     *
     * @param  array<int, Component>  $components
     */
    private function moreOptions(string $description, array $components): Section
    {
        return Section::make(__('More options'))
            ->description($description)
            ->collapsible()
            ->collapsed()
            ->compact()
            ->schema($components);
    }

    /**
     * Text colours checked against a typical background: white for dark text, near-black for light text.
     *
     * @param  array<string, string>  $fields  field => label
     */
    private function textColors(string $title, array $fields, string $typicalBackground): Fieldset
    {
        return Fieldset::make($title)
            ->columns(2)
            ->schema(array_map(
                fn (string $name, string $label): ColorPicker => $this->colorField($name, $label)
                    ->live()
                    ->helperText(fn (?string $state): ?string => $this->isUnreadable($state, $typicalBackground)
                        ? __('Warning: this may be hard to read on :background backgrounds.', ['background' => $typicalBackground === '#ffffff' ? __('light') : __('dark')])
                        : null),
                array_keys($fields),
                $fields,
            ));
    }

    private function colorField(string $name, string $label): ColorPicker
    {
        return ColorPicker::make($name)
            ->label($label)
            ->regex('/^#[0-9a-fA-F]{6}$/')
            ->validationMessages(['regex' => __('Enter a 6-digit hex colour, for example #1d41d5.')]);
    }

    /**
     * @param  array<int, string>  $fileTypes
     */
    private function uploadField(string $name, string $label, array $fileTypes, int $maxSizeInKilobytes): FileUpload
    {
        $branding = app(Branding::class);

        return FileUpload::make($name)
            ->label($label)
            ->disk($branding->uploadDisk())
            ->directory($branding->uploadDirectory())
            ->visibility('public')
            ->acceptedFileTypes($fileTypes)
            ->maxSize($maxSizeInKilobytes)
            // An SVG is served from the site's own address, so only plain drawings are accepted (no scripts or links).
            ->rule(new SafeSvg)
            // Filament only trusts existing paths from an Eloquent record; there is none here, so trust exactly the currently saved file.
            ->preventFilePathTampering(
                allowFilePathUsing: fn (string $file): bool => $file === app(Branding::class)->string($this->fields()[$name]),
            );
    }

    private function siteColorHelperText(?string $state): string
    {
        if (Colour::isValidHex($state) && ! Colour::hasAccessibleButtonContrast($state)) {
            return __('Warning: white text on buttons in this colour may be hard to read. Consider a darker shade.');
        }

        return __('The main colour of the public site: buttons, links and highlights.');
    }

    /**
     * Filament leaves replaced or removed files on the disk, so tidy up the old ones.
     *
     * @param  array<string, string|null>  $previousUploads
     * @param  array<string, mixed>  $data
     */
    private function deleteReplacedUploads(Branding $branding, array $previousUploads, array $data): void
    {
        foreach ($previousUploads as $field => $previousPath) {
            // A hidden field (a banner image while the banner is a gradient) isn't in the data: keep its file.
            if (filled($previousPath) && array_key_exists($field, $data) && $previousPath !== $data[$field]) {
                Storage::disk($branding->uploadDisk())->delete($previousPath);
            }
        }
    }
}
