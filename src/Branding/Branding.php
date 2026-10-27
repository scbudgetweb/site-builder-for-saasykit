<?php

namespace ScBudgetWeb\SiteBuilder\Branding;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Settings\Settings;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Site identity (Admin › Website › Branding): logos, favicon, colours, fonts, text sizes, header and footer
 * colours, banners, dashboard colours and custom CSS for the public site and the Filament panels.
 *
 * Every setting is optional: blank keeps the standard look, so a fresh install looks exactly as before.
 * Values are stored in the package's settings table under "branding.*"; BrandingStyle owns the Style keys.
 */
class Branding
{
    public const SITE_COLOR = 'branding.site_color';

    public const SITE_SECONDARY_COLOR = 'branding.site_secondary_color';

    public const SITE_FONT = 'branding.site_font';

    /** Heading fonts. Blank (or the body font) means the body font. */
    public const SITE_HEADING_FONT = 'branding.site_heading_font';

    public const APP_FONT = 'branding.app_font';

    public const APP_HEADING_FONT = 'branding.app_heading_font';

    /** Body text size in px (TEXT_SIZE_OPTIONS) and heading scale (HEADING_SCALE_OPTIONS), D0a-9. */
    public const TEXT_SIZE = 'branding.text_size';

    public const HEADING_SCALE = 'branding.heading_scale';

    public const LOGO_LIGHT = 'branding.logo_light';

    public const LOGO_DARK = 'branding.logo_dark';

    public const LOGO_IN_PANELS = 'branding.logo_in_panels';

    /** Which logo each place shows: "light" (the logo for light backgrounds) or "dark". */
    public const LOGO_HEADER = 'branding.logo_header';

    public const LOGO_FOOTER = 'branding.logo_footer';

    public const LOGO_AUTH = 'branding.logo_auth';

    public const LOGO_DASHBOARDS = 'branding.logo_dashboards';

    /** Place key => [label, the logo it uses by default]. */
    public const LOGO_PLACES = [
        self::LOGO_HEADER => ['Header', 'light'],
        self::LOGO_FOOTER => ['Footer', 'dark'],
        self::LOGO_AUTH => ['Sign-in screens & emails', 'light'],
        self::LOGO_DASHBOARDS => ['Dashboards', 'light'],
    ];

    public const FAVICON = 'branding.favicon';

    /** Apple touch icon (home-screen icon on iPhones and iPads), D0a-10. */
    public const APPLE_TOUCH_ICON = 'branding.apple_touch_icon';

    /** The image shown when a page is shared on social media, unless the page has its own (Pages › SEO). */
    public const SHARE_IMAGE = 'branding.share_image';

    public const TEXT_DARK_HEADING_COLOR = 'branding.text_dark_heading_color';

    public const TEXT_DARK_BODY_COLOR = 'branding.text_dark_body_color';

    public const TEXT_LIGHT_HEADING_COLOR = 'branding.text_light_heading_color';

    public const TEXT_LIGHT_BODY_COLOR = 'branding.text_light_body_color';

    public const HEADER_BG_COLOR = 'branding.header_bg_color';

    public const HEADER_TEXT_COLOR = 'branding.header_text_color';

    public const HEADER_LINK_COLOR = 'branding.header_link_color';

    public const HEADER_LINK_HOVER_COLOR = 'branding.header_link_hover_color';

    public const HEADER_MENU_BG_COLOR = 'branding.header_menu_bg_color';

    public const HEADER_MENU_LINK_COLOR = 'branding.header_menu_link_color';

    public const HEADER_MENU_LINK_HOVER_COLOR = 'branding.header_menu_link_hover_color';

    public const HEADER_MENU_HEADING_COLOR = 'branding.header_menu_heading_color';

    public const HEADER_BUTTON_COLOR = 'branding.header_button_color';

    public const HEADER_BUTTON_HOVER_COLOR = 'branding.header_button_hover_color';

    public const HEADER_BUTTON_TEXT_COLOR = 'branding.header_button_text_color';

    public const HEADER_BUTTON_TEXT_HOVER_COLOR = 'branding.header_button_text_hover_color';

    public const FOOTER_BG_COLOR = 'branding.footer_bg_color';

    public const FOOTER_TEXT_COLOR = 'branding.footer_text_color';

    public const FOOTER_LINK_COLOR = 'branding.footer_link_color';

    public const FOOTER_LINK_HOVER_COLOR = 'branding.footer_link_hover_color';

    public const FOOTER_SOCIAL_COLOR = 'branding.footer_social_color';

    public const FOOTER_SOCIAL_HOVER_COLOR = 'branding.footer_social_hover_color';

    /** SaaSykit's announcement bar (Admin › Announcements), on the website and in the dashboard. */
    public const ANNOUNCEMENT_BG_COLOR = 'branding.announcement_bg_color';

    public const ANNOUNCEMENT_TEXT_COLOR = 'branding.announcement_text_color';

    public const ANNOUNCEMENT_LINK_COLOR = 'branding.announcement_link_color';

    public const ANNOUNCEMENT_LINK_HOVER_COLOR = 'branding.announcement_link_hover_color';

    /** Admin-written CSS for the public site, checked by Support\CustomCss. */
    public const CUSTOM_CSS = 'branding.custom_css';

    /**
     * Element colour settings => the CSS variables (site-builder.css) each one sets.
     * Some settings drive two variables whose defaults differ, e.g. menu links and account links.
     *
     * @var array<string, array<int, string>>
     */
    public const ELEMENT_COLOR_VARIABLES = [
        self::TEXT_DARK_HEADING_COLOR => ['--color-sb-scheme-dark-heading'],
        self::TEXT_DARK_BODY_COLOR => ['--color-sb-scheme-dark-body'],
        self::TEXT_LIGHT_HEADING_COLOR => ['--color-sb-scheme-light-heading'],
        self::TEXT_LIGHT_BODY_COLOR => ['--color-sb-scheme-light-body'],
        self::HEADER_BG_COLOR => ['--color-sb-header-bg'],
        self::HEADER_TEXT_COLOR => ['--color-sb-header-text'],
        self::HEADER_LINK_COLOR => ['--color-sb-header-link', '--color-sb-header-account-link'],
        self::HEADER_LINK_HOVER_COLOR => ['--color-sb-header-link-hover', '--color-sb-header-account-link-hover'],
        self::HEADER_MENU_BG_COLOR => ['--color-sb-header-menu-bg'],
        self::HEADER_MENU_LINK_COLOR => ['--color-sb-header-menu-link'],
        self::HEADER_MENU_LINK_HOVER_COLOR => ['--color-sb-header-menu-link-hover'],
        self::HEADER_MENU_HEADING_COLOR => ['--color-sb-header-menu-heading'],
        self::HEADER_BUTTON_COLOR => ['--color-sb-header-button'],
        self::HEADER_BUTTON_HOVER_COLOR => ['--color-sb-header-button-hover'],
        self::HEADER_BUTTON_TEXT_COLOR => ['--color-sb-header-button-text'],
        self::HEADER_BUTTON_TEXT_HOVER_COLOR => ['--color-sb-header-button-text-hover'],
        self::FOOTER_BG_COLOR => ['--color-sb-footer-bg'],
        self::FOOTER_TEXT_COLOR => ['--color-sb-footer-text', '--color-sb-footer-heading'],
        self::FOOTER_LINK_COLOR => ['--color-sb-footer-link'],
        self::FOOTER_LINK_HOVER_COLOR => ['--color-sb-footer-link-hover'],
        self::FOOTER_SOCIAL_COLOR => ['--color-sb-footer-social'],
        self::FOOTER_SOCIAL_HOVER_COLOR => ['--color-sb-footer-social-hover'],
        self::ANNOUNCEMENT_BG_COLOR => ['--color-sb-announcement-bg'],
        self::ANNOUNCEMENT_TEXT_COLOR => ['--color-sb-announcement-text'],
        self::ANNOUNCEMENT_LINK_COLOR => ['--color-sb-announcement-link'],
        self::ANNOUNCEMENT_LINK_HOVER_COLOR => ['--color-sb-announcement-link-hover'],
    ];

    /**
     * Text colour => background colour pairs checked for readable contrast (WCAG AA) when both are set.
     *
     * @var array<string, string>
     */
    public const CONTRAST_PAIRS = [
        self::HEADER_TEXT_COLOR => self::HEADER_BG_COLOR,
        self::HEADER_LINK_COLOR => self::HEADER_BG_COLOR,
        self::HEADER_LINK_HOVER_COLOR => self::HEADER_BG_COLOR,
        self::HEADER_MENU_LINK_COLOR => self::HEADER_MENU_BG_COLOR,
        self::HEADER_MENU_LINK_HOVER_COLOR => self::HEADER_MENU_BG_COLOR,
        self::HEADER_MENU_HEADING_COLOR => self::HEADER_MENU_BG_COLOR,
        self::HEADER_BUTTON_TEXT_COLOR => self::HEADER_BUTTON_COLOR,
        self::HEADER_BUTTON_TEXT_HOVER_COLOR => self::HEADER_BUTTON_HOVER_COLOR,
        self::FOOTER_TEXT_COLOR => self::FOOTER_BG_COLOR,
        self::FOOTER_LINK_COLOR => self::FOOTER_BG_COLOR,
        self::FOOTER_LINK_HOVER_COLOR => self::FOOTER_BG_COLOR,
        self::FOOTER_SOCIAL_COLOR => self::FOOTER_BG_COLOR,
        self::FOOTER_SOCIAL_HOVER_COLOR => self::FOOTER_BG_COLOR,
        self::ANNOUNCEMENT_TEXT_COLOR => self::ANNOUNCEMENT_BG_COLOR,
        self::ANNOUNCEMENT_LINK_COLOR => self::ANNOUNCEMENT_BG_COLOR,
        self::ANNOUNCEMENT_LINK_HOVER_COLOR => self::ANNOUNCEMENT_BG_COLOR,
    ];

    /** Body text size: value (px) => label. 16 is the standard size. */
    public const TEXT_SIZE_OPTIONS = [
        '15' => 'Small (15px)',
        '16' => 'Standard (16px)',
        '17' => 'Large (17px)',
        '18' => 'Extra large (18px)',
    ];

    /** Heading scale: value => [label, multiplier]. */
    public const HEADING_SCALE_OPTIONS = [
        'compact' => ['Compact', '0.9'],
        'standard' => ['Standard', '1'],
        'large' => ['Large', '1.15'],
    ];

    /** Page banners: "hero" is the home page, "inner" every other page with a banner. */
    public const BANNERS = ['hero', 'inner'];

    /** Each banner's fields, stored as "branding.{banner}_{field}" (see bannerKey()). */
    public const BANNER_FIELDS = [
        'background_type',
        'background_color',
        'gradient_start',
        'gradient_middle',
        'gradient_end',
        'gradient_direction',
        'image',
        'image_position',
        'overlay_enabled',
        'overlay_type',
        'overlay_direction',
        'overlay_color',
        'overlay_opacity',
        'overlay_end_color',
        'overlay_end_opacity',
        'heading_color',
        'subtitle_color',
    ];

    public const BANNER_TYPE_GRADIENT = 'gradient';

    public const BANNER_TYPE_COLOR = 'color';

    public const BANNER_TYPE_IMAGE = 'image';

    /**
     * SaaSykit's standard primary shades (resources/css/colors.css), used for the default banner gradient and
     * its contrast check when no site colour is set.
     *
     * @var array<int, string>
     */
    private const DEFAULT_PRIMARY_SHADES = [100 => '#d2bcf7', 800 => '#3d157e', 900 => '#2f1060', 950 => '#1f0a3f'];

    public const DEFAULT_SITE_FONT = 'poppins';

    public const UPLOAD_SUBFOLDER = 'branding';

    /** Fonts shipped with the package (published here by site-builder:install). */
    public const PACKAGE_FONTS_DIRECTORY = 'vendor/site-builder/fonts';

    /** The host app's own extra fonts. */
    public const APP_FONTS_DIRECTORY = 'fonts/site-builder';

    public function __construct(private Settings $settings) {}

    /**
     * Every setting key (including the generated banner and panel colour keys, and BrandingStyle's).
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [
            self::SITE_COLOR, self::SITE_SECONDARY_COLOR,
            self::SITE_FONT, self::SITE_HEADING_FONT, self::APP_FONT, self::APP_HEADING_FONT,
            self::TEXT_SIZE, self::HEADING_SCALE,
            self::LOGO_LIGHT, self::LOGO_DARK, self::LOGO_IN_PANELS,
            self::LOGO_HEADER, self::LOGO_FOOTER, self::LOGO_AUTH, self::LOGO_DASHBOARDS,
            self::FAVICON, self::APPLE_TOUCH_ICON, self::SHARE_IMAGE,
            ...array_keys(self::ELEMENT_COLOR_VARIABLES),
            self::CUSTOM_CSS,
        ];

        foreach (self::BANNERS as $banner) {
            foreach (self::BANNER_FIELDS as $field) {
                $keys[] = self::bannerKey($banner, $field);
            }
        }

        return array_values(array_unique([...$keys, ...BrandingStyle::keys()]));
    }

    /**
     * The app's own element colours (SiteBuilder::brandingColours()).
     *
     * @return array<string, ColourGroup>
     */
    public function appColourGroups(): array
    {
        return app(SiteBuilder::class)->registeredBrandingColours();
    }

    /**
     * The setting keys of the app's own element colours.
     *
     * @return array<int, string>
     */
    public function appColourKeys(): array
    {
        return collect($this->appColourGroups())
            ->flatMap(fn (ColourGroup $group): array => array_map(fn (string $key): string => ColourGroup::settingKey($key), array_keys($group->colours)))
            ->values()
            ->all();
    }

    /**
     * The app's own Branding sections (SiteBuilder::brandingSection()).
     *
     * @return array<string, BrandingSection>
     */
    public function appSections(): array
    {
        return app(SiteBuilder::class)->registeredBrandingSections();
    }

    /**
     * Every field in the app's own sections, by key.
     *
     * @return array<string, BrandingField>
     */
    public function appFields(): array
    {
        return array_merge([], ...array_map(fn (BrandingSection $section): array => $section->fields, array_values($this->appSections())));
    }

    /**
     * The setting keys the app's own sections save.
     *
     * @return array<int, string>
     */
    public function appSectionKeys(): array
    {
        return array_merge([], ...array_map(fn (BrandingSection $section): array => $section->settingKeys(), array_values($this->appSections())));
    }

    /**
     * A setting from the app's own sections, re-checked (BrandingField::clean()): the saved value if it's valid,
     * otherwise the field's default. Null for an unknown key or a banner (use banner('app_{key}')).
     */
    public function appValue(string $key): mixed
    {
        $field = $this->appFields()[$key] ?? null;

        if ($field === null || $field->type === BrandingField::BANNER) {
            return null;
        }

        return $field->clean($this->get($field->settingKey())) ?? $field->getDefault();
    }

    /**
     * The URL of an image from the app's own sections, or null when none is saved (or the path isn't a Branding upload).
     */
    public function appImageUrl(string $key): ?string
    {
        $field = $this->appFields()[$key] ?? null;
        $path = $field?->type === BrandingField::IMAGE ? $field->clean($this->get($field->settingKey())) : null;

        return $path !== null ? $this->uploadUrl($path) : null;
    }

    /**
     * Every banner: the hero and inner banners, then each banner in the app's own sections ("app_{key}").
     *
     * @return array<int, string>
     */
    public function banners(): array
    {
        $appBanners = array_map(
            fn (BrandingField $field): string => $field->bannerName(),
            array_filter($this->appFields(), fn (BrandingField $field): bool => $field->type === BrandingField::BANNER),
        );

        return [...self::BANNERS, ...array_values($appBanners)];
    }

    /**
     * Text colour => background colour setting pairs checked for readable contrast: the built-in ones and the app's.
     *
     * @return array<string, string>
     */
    public function contrastPairs(): array
    {
        $pairs = self::CONTRAST_PAIRS;

        foreach ($this->appColourGroups() as $group) {
            foreach ($group->contrastPairs as $text => $background) {
                $pairs[ColourGroup::settingKey($text)] = ColourGroup::settingKey($background);
            }
        }

        foreach ($this->appFields() as $field) {
            if ($field->type === BrandingField::COLOUR && $field->getContrastWith() !== null) {
                $pairs[$field->settingKey()] = ColourGroup::settingKey($field->getContrastWith());
            }
        }

        return $pairs;
    }

    public static function bannerKey(string $banner, string $field): string
    {
        return "branding.{$banner}_{$field}";
    }

    public static function panelColorKey(string $panelId): string
    {
        return 'branding.panel_color_'.Str::slug($panelId, '_');
    }

    public function get(string $key): mixed
    {
        return $this->settings->get($key);
    }

    /**
     * A setting that must be a string (anything else, e.g. a hand-edited array, reads as null).
     */
    public function string(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    public function isOn(string $key): bool
    {
        return in_array($this->settings->get($key), [true, 1, '1'], true);
    }

    /**
     * Saves Branding settings (unknown keys are ignored). Blank values are removed, so the default applies again.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $allowed = array_flip([...self::keys(), ...$this->appColourKeys(), ...$this->appSectionKeys()]);

        $this->settings->set(array_filter(
            $values,
            fn (string $key): bool => isset($allowed[$key]) || str_starts_with($key, 'branding.panel_color_'),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    // Fonts ------------------------------------------------------------------------------------------

    /**
     * Fonts are folders containing a font.css, in the package's published fonts folder or the app's own
     * public/fonts/site-builder. The folder name is the slug and, with separators as spaces and title-cased,
     * the CSS font-family name. An app font with the same slug wins.
     *
     * @return array<string, string> slug => family name
     */
    public function availableFonts(): array
    {
        return once(function (): array {
            $fonts = [];

            foreach ([self::PACKAGE_FONTS_DIRECTORY, self::APP_FONTS_DIRECTORY] as $directory) {
                if (! File::isDirectory(public_path($directory))) {
                    continue;
                }

                foreach (File::directories(public_path($directory)) as $folder) {
                    if (File::exists($folder.'/font.css') && preg_match('/^[a-z0-9_-]+$/', basename($folder)) === 1) {
                        $fonts[basename($folder)] = Str::of(basename($folder))->replace(['_', '-'], ' ')->title()->toString();
                    }
                }
            }

            ksort($fonts);

            return $fonts;
        });
    }

    public function fontFamily(string $slug): string
    {
        // Callers only pass available fonts' slugs; anything else is reduced to letters, digits and spaces, because the
        // name goes into CSS.
        return $this->availableFonts()[$slug]
            ?? Str::of($slug)->replace(['_', '-'], ' ')->replaceMatches('/[^A-Za-z0-9 ]/', '')->title()->trim()->toString();
    }

    public function fontStylesheetUrl(string $slug): string
    {
        $directory = File::exists(public_path(self::APP_FONTS_DIRECTORY."/{$slug}/font.css"))
            ? self::APP_FONTS_DIRECTORY
            : self::PACKAGE_FONTS_DIRECTORY;

        return asset("{$directory}/{$slug}/font.css");
    }

    /**
     * The public site's body font. Poppins (SaaSykit's own font, now self-hosted) unless another is chosen.
     */
    public function siteFont(): string
    {
        $slug = $this->string(self::SITE_FONT);

        return $slug !== null && isset($this->availableFonts()[$slug]) ? $slug : self::DEFAULT_SITE_FONT;
    }

    public function siteHeadingFont(): ?string
    {
        return $this->headingFontOrNull($this->string(self::SITE_HEADING_FONT), $this->siteFont());
    }

    /**
     * The dashboards' font, or null to keep each panel's own font (set in its panel provider).
     */
    public function appFont(): ?string
    {
        $slug = $this->string(self::APP_FONT);

        return $slug !== null && isset($this->availableFonts()[$slug]) ? $slug : null;
    }

    public function appHeadingFont(): ?string
    {
        return $this->headingFontOrNull($this->string(self::APP_HEADING_FONT), (string) $this->appFont());
    }

    /**
     * Dashboard headings in the heading font: a stylesheet link and a rule, for the panels' head. Empty when
     * there's no separate heading font.
     */
    public function panelHeadingFontHtml(): string
    {
        $font = $this->appHeadingFont();

        if ($font === null) {
            return '';
        }

        $family = str_replace("'", '', $this->fontFamily($font));

        return '<link rel="stylesheet" href="'.e($this->fontStylesheetUrl($font)).'">'
            ."<style>h1, h2, h3, .fi-header-heading, .fi-section-header-heading, .fi-modal-heading, .fi-ta-header-heading { font-family: '{$family}', var(--font-family), ui-sans-serif, system-ui, sans-serif; }</style>";
    }

    // Colours ----------------------------------------------------------------------------------------

    /**
     * A panel's primary palette, or null to keep the colour set in its panel provider.
     *
     * @return array<int, string>|null
     */
    public function panelPalette(string $panelId): ?array
    {
        $color = $this->string(self::panelColorKey($panelId));

        return Colour::isValidHex($color) ? Colour::palette($color) : null;
    }

    /**
     * CSS custom-property overrides for the public site. Only configured settings are emitted, so the
     * compiled defaults apply otherwise. Element colours are used exactly as picked (no palette).
     */
    public function siteCssVariables(): string
    {
        $variables = [];

        if (Colour::isValidHex($siteColor = $this->string(self::SITE_COLOR))) {
            foreach (Colour::palette($siteColor) as $shade => $color) {
                $variables[] = "--color-primary-{$shade}: {$color}";
            }
        }

        if (Colour::isValidHex($secondaryColor = $this->string(self::SITE_SECONDARY_COLOR))) {
            foreach (Colour::palette($secondaryColor) as $shade => $color) {
                $variables[] = "--color-secondary-{$shade}: {$color}";
            }
        }

        foreach (self::ELEMENT_COLOR_VARIABLES as $key => $cssVariables) {
            if (Colour::isValidHex($color = $this->string($key))) {
                foreach ($cssVariables as $cssVariable) {
                    $variables[] = "{$cssVariable}: {$color}";
                }
            }
        }

        foreach ($this->appColourGroups() as $group) {
            foreach ($group->colours as $key => $colour) {
                if (Colour::isValidHex($color = $this->string(ColourGroup::settingKey($key)))) {
                    foreach ($colour['variables'] as $cssVariable) {
                        $variables[] = "{$cssVariable}: {$color}";
                    }
                }
            }
        }

        foreach ($this->appFields() as $field) {
            if ($field->type === BrandingField::COLOUR && ($color = $this->appValue($field->key)) !== null) {
                foreach ($field->getVariables() as $cssVariable) {
                    $variables[] = "{$cssVariable}: {$color}";
                }
            }
        }

        foreach ($this->banners() as $banner) {
            foreach (['heading', 'subtitle'] as $text) {
                if (Colour::isValidHex($color = $this->string(self::bannerKey($banner, "{$text}_color")))) {
                    $variables[] = "--color-sb-{$banner}-{$text}: {$color}";
                }
            }
        }

        if ($this->string(self::SITE_FONT) !== null && isset($this->availableFonts()[$this->string(self::SITE_FONT)])) {
            $variables[] = "--font-sans: '{$this->fontFamily($this->siteFont())}', ui-sans-serif, system-ui, sans-serif";
        }

        if ($headingFont = $this->siteHeadingFont()) {
            $variables[] = "--font-sb-heading: '{$this->fontFamily($headingFont)}', ui-sans-serif, system-ui, sans-serif";
        }

        if (isset(self::TEXT_SIZE_OPTIONS[(string) $this->string(self::TEXT_SIZE)])) {
            $variables[] = '--sb-text-base: '.((int) $this->string(self::TEXT_SIZE) / 16).'rem';
        }

        if (isset(self::HEADING_SCALE_OPTIONS[(string) $this->string(self::HEADING_SCALE)])) {
            $variables[] = '--sb-heading-scale: '.self::HEADING_SCALE_OPTIONS[$this->string(self::HEADING_SCALE)][1];
        }

        return $variables === [] ? '' : ':root { '.implode('; ', $variables).'; }';
    }

    /**
     * A site colour's palette shade, or SaaSykit's standard primary shade when no site colour is set.
     */
    public function primaryShade(int $shade): string
    {
        $siteColor = $this->string(self::SITE_COLOR);

        return Colour::isValidHex($siteColor) ? Colour::shade($siteColor, $shade) : self::DEFAULT_PRIMARY_SHADES[$shade];
    }

    // Banners ----------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function bannerValues(string $banner): array
    {
        $values = [];

        foreach (self::BANNER_FIELDS as $field) {
            $values[$field] = $this->get(self::bannerKey($banner, $field));
        }

        return $values;
    }

    /**
     * What a banner should render. A given image (e.g. a Banner block's own image) wins over the config.
     * Every value is validated again here, so a bad database value can't inject CSS.
     *
     * @param  array{color: string, opacity: float, gradient?: ?string}|null  $imageOverlay  as from BlockAppearance::imageOverlay()
     * @return array{image: ?string, imagePosition: string, overlay: ?array{color: string, opacity: float, gradient: ?string}, gradient: ?string, color: ?string}
     */
    public function banner(string $banner, ?string $imageUrl = null, ?array $imageOverlay = null, ?string $imagePosition = null): array
    {
        $values = $this->bannerValues(in_array($banner, $this->banners(), true) ? $banner : 'inner');

        if ($imageUrl !== null) {
            return [
                'image' => $imageUrl,
                'imagePosition' => array_key_exists((string) $imagePosition, Colour::IMAGE_POSITIONS) ? $imagePosition : 'center',
                'overlay' => $imageOverlay !== null
                    ? ['color' => $imageOverlay['color'], 'opacity' => $imageOverlay['opacity'], 'gradient' => $imageOverlay['gradient'] ?? null]
                    : ['color' => '#030712', 'opacity' => 0.6, 'gradient' => null],
                'gradient' => null,
                'color' => null,
            ];
        }

        $color = $this->solidBackgroundColor($values);

        // A banner's image must be a Branding upload, not any file on the disk (lesson 11).
        $configImage = ($values['background_type'] ?? null) === self::BANNER_TYPE_IMAGE && BrandingField::isBrandingUpload($values['image'] ?? null)
            ? $this->uploadUrl($values['image'])
            : null;

        return [
            'image' => $configImage,
            'imagePosition' => array_key_exists((string) ($values['image_position'] ?? ''), Colour::IMAGE_POSITIONS) ? $values['image_position'] : 'center',
            'overlay' => $configImage !== null && in_array($values['overlay_enabled'] ?? null, [true, 1, '1'], true)
                ? BlockAppearance::imageOverlay($values)
                : null,
            'gradient' => $configImage === null && $color === null ? Colour::gradientCss($values) : null,
            'color' => $configImage === null ? $color : null,
        ];
    }

    /**
     * Banner text colours that would be hard to read, as "heading" / "subtitle". Image banners aren't checked
     * here (an image can't be measured); see bannerImageNeedsOverlay().
     *
     * @param  array<string, mixed>  $values  banner field => value (saved or unsaved form state)
     * @return array<int, string>
     */
    public function unreadableBannerText(array $values): array
    {
        if (($values['background_type'] ?? null) === self::BANNER_TYPE_IMAGE) {
            return [];
        }

        $color = $this->solidBackgroundColor($values);
        $stops = $color !== null
            ? [$color]
            : (Colour::gradientStops($values) ?? [$this->primaryShade(900), $this->primaryShade(800), $this->primaryShade(950)]);
        $unreadable = [];

        foreach (['heading' => '#ffffff', 'subtitle' => $this->primaryShade(100)] as $text => $defaultColor) {
            $textColor = Colour::isValidHex($values["{$text}_color"] ?? null) ? $values["{$text}_color"] : $defaultColor;

            foreach ($stops as $stop) {
                if (! Colour::isReadableTextOn($textColor, $stop)) {
                    $unreadable[] = $text;

                    break;
                }
            }
        }

        return $unreadable;
    }

    /**
     * @param  array<string, mixed>  $values  banner field => value
     */
    public function bannerImageNeedsOverlay(array $values): bool
    {
        if (($values['background_type'] ?? null) !== self::BANNER_TYPE_IMAGE || blank($values['image'] ?? null)) {
            return false;
        }

        // A gradient counts by its stronger end: it's usually darkest behind the text and fades away from it.
        return ! filter_var($values['overlay_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || round(BlockAppearance::imageOverlay($values)['opacity'] * 100) < Colour::MIN_READABLE_OVERLAY_OPACITY;
    }

    /**
     * The announcement bar's colours, each a validated hex colour or null for SaaSykit's standard look. The theme's
     * announcement view uses them as inline styles, so they also work in the dashboard, whose stylesheet doesn't
     * include the site's colour utilities.
     *
     * @return array{background: ?string, text: ?string, link: ?string, linkHover: ?string}
     */
    public function announcementColours(): array
    {
        $colour = fn (string $key): ?string => Colour::isValidHex($this->get($key)) ? $this->get($key) : null;

        return [
            'background' => $colour(self::ANNOUNCEMENT_BG_COLOR),
            'text' => $colour(self::ANNOUNCEMENT_TEXT_COLOR),
            'link' => $colour(self::ANNOUNCEMENT_LINK_COLOR),
            'linkHover' => $colour(self::ANNOUNCEMENT_LINK_HOVER_COLOR),
        ];
    }

    // Logos and icons --------------------------------------------------------------------------------

    public function logoUrl(string $variant): ?string
    {
        $path = $this->string($variant === 'dark' ? self::LOGO_DARK : self::LOGO_LIGHT);

        return $path ? $this->uploadUrl($path) : null;
    }

    /**
     * Which logo a place uses (one of the LOGO_PLACES keys): "light" or "dark".
     */
    public function logoVariantFor(string $place): string
    {
        $variant = $this->string($place);

        return in_array($variant, ['light', 'dark'], true) ? $variant : (self::LOGO_PLACES[$place][1] ?? 'light');
    }

    /**
     * The logo chosen for a place, or null when that logo hasn't been uploaded.
     */
    public function logoUrlFor(string $place): ?string
    {
        return $this->logoUrl($this->logoVariantFor($place));
    }

    public function showsLogoInPanels(): bool
    {
        return $this->isOn(self::LOGO_IN_PANELS);
    }

    public function faviconUrl(): string
    {
        $path = $this->string(self::FAVICON);

        return $path ? $this->uploadUrl($path) : asset('images/favicon.ico');
    }

    public function appleTouchIconUrl(): ?string
    {
        $path = $this->string(self::APPLE_TOUCH_ICON);

        return $path ? $this->uploadUrl($path) : null;
    }

    /**
     * The default social share image, or null to keep SaaSykit's standard cards.
     */
    public function shareImageUrl(): ?string
    {
        $path = $this->string(self::SHARE_IMAGE);

        return $path ? $this->uploadUrl($path) : null;
    }

    /**
     * The Branding uploads that settings use (logos, icons, the share image, banner images and app sections' images):
     * every saved Branding value that's a file in the Branding upload folder. site-builder:clean-uploads keeps these.
     *
     * @return array<int, string>
     */
    public function usedUploads(): array
    {
        return array_values(array_unique(array_filter(
            $this->settings->startingWith('branding.'),
            fn (mixed $value): bool => BrandingField::isBrandingUpload($value),
        )));
    }

    public function uploadDisk(): string
    {
        return app(SiteBuilder::class)->uploadDisk();
    }

    public function uploadDirectory(): string
    {
        return app(SiteBuilder::class)->uploadDirectory(self::UPLOAD_SUBFOLDER);
    }

    private function uploadUrl(string $path): string
    {
        return Storage::disk($this->uploadDisk())->url($path);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function solidBackgroundColor(array $values): ?string
    {
        return ($values['background_type'] ?? null) === self::BANNER_TYPE_COLOR && Colour::isValidHex($values['background_color'] ?? null)
            ? $values['background_color']
            : null;
    }

    private function headingFontOrNull(?string $slug, string $bodyFont): ?string
    {
        return $slug !== null && $slug !== $bodyFont && isset($this->availableFonts()[$slug]) ? $slug : null;
    }
}
