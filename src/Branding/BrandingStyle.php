<?php

namespace ScBudgetWeb\SiteBuilder\Branding;

use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * The style part of Admin › Website › Branding: corner shapes, shadows, section headings and the four
 * named button styles. Values are stored by Branding (these keys are part of its keys()); this class
 * owns the choices and turns the saved values into CSS.
 *
 * Every choice is a key from a fixed list and every colour a validated hex value, so the generated CSS
 * never contains admin-typed text. With nothing saved it outputs nothing and the compiled defaults
 * in site-builder.css apply, which reproduce the standard look.
 */
class BrandingStyle
{
    public const BUTTON_CORNERS = 'branding.style_button_corners';

    public const CARD_CORNERS = 'branding.style_card_corners';

    public const IMAGE_CORNERS = 'branding.style_image_corners';

    public const INPUT_CORNERS = 'branding.style_input_corners';

    public const SHADOWS = 'branding.style_shadows';

    public const HEADING_WEIGHT = 'branding.style_heading_weight';

    public const HEADING_DECORATION = 'branding.style_heading_decoration';

    public const HEADING_DECORATION_COLOR = 'branding.style_heading_decoration_color';

    /** The decoration colour in blocks with a dark background (blank: the heading's own text colour). */
    public const HEADING_DECORATION_DARK_COLOR = 'branding.style_heading_decoration_dark_color';

    /** The button style used by "Automatic" buttons on light and on dark backgrounds. */
    public const BUTTON_ON_LIGHT = 'branding.button_on_light';

    public const BUTTON_ON_DARK = 'branding.button_on_dark';

    public const BUTTON_SLOTS = [1, 2, 3, 4];

    /**
     * Fields of each button style, stored as "branding.button_{slot}_{field}" (see buttonKey()).
     * Colour fields map to the .site-button CSS variable they set.
     *
     * @var array<string, string|null>
     */
    public const BUTTON_FIELDS = [
        'name' => null,
        'bg' => '--site-button-bg',
        'bg_hover' => '--site-button-bg-hover',
        'text' => '--site-button-text',
        'text_hover' => '--site-button-text-hover',
        'border' => '--site-button-border',
        'border_hover' => '--site-button-border-hover',
        'uppercase' => null,
        'lift' => null,
    ];

    /** Option key => [label, CSS value]. The first entry of each list marked default is today's look. */
    public const BUTTON_CORNER_OPTIONS = [
        'square' => ['Square', '0px'],
        'slight' => ['Slightly rounded', '0.25rem'],
        'rounded' => ['Rounded (default)', '0.5rem'],
        'pill' => ['Pill', '9999px'],
    ];

    public const CARD_CORNER_OPTIONS = [
        'none' => ['None', '0px'],
        'small' => ['Small', '0.375rem'],
        'medium' => ['Medium (default)', '0.75rem'],
        'large' => ['Large', '1.5rem'],
    ];

    public const IMAGE_CORNER_OPTIONS = [
        'none' => ['None', '0px'],
        'small' => ['Small', '0.375rem'],
        'medium' => ['Medium (default)', '1rem'],
        'large' => ['Large', '1.5rem'],
    ];

    /** "match" follows the button corners (the default). */
    public const INPUT_CORNER_OPTIONS = [
        'match' => ['Match the buttons (default)', 'var(--radius-sb-button)'],
        'square' => ['Square', '0px'],
        'slight' => ['Slightly rounded', '0.25rem'],
        'rounded' => ['Rounded', '0.5rem'],
    ];

    public const SHADOW_OPTIONS = [
        'none' => ['None', 'none'],
        'soft' => ['Soft (default)', '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)'],
        'strong' => ['Strong', '0 10px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1)'],
    ];

    public const HEADING_WEIGHT_OPTIONS = [
        '600' => ['Semi-bold', '600'],
        '700' => ['Bold (default)', '700'],
        '800' => ['Extra-bold', '800'],
    ];

    public const HEADING_DECORATION_OPTIONS = [
        'none' => 'None (default)',
        'short_under' => 'Short bar under the heading',
        'line_under' => 'Full-width line under the heading',
        'short_above' => 'Short bar above the heading',
    ];

    /**
     * The decoration rules. The bar follows the heading's alignment: centred headings (or headings in a
     * centred block) get a centred bar.
     */
    private const DECORATION_CSS = [
        'short_under' => '.section-heading::after { display: block; width: 3rem; height: 4px; border-radius: 9999px; margin-top: 0.75rem; }'
            .' .text-center .section-heading::after, .section-heading.text-center::after { margin-inline: auto; }',
        'line_under' => '.section-heading::after { display: block; width: 100%; height: 2px; margin-top: 0.75rem; }',
        'short_above' => '.section-heading::before { display: block; width: 3rem; height: 4px; border-radius: 9999px; margin-bottom: 0.75rem; }'
            .' .text-center .section-heading::before, .section-heading.text-center::before { margin-inline: auto; }',
    ];

    /** The pre-filled names, matching the original block button styles. */
    public const DEFAULT_BUTTON_NAMES = [1 => 'Primary', 2 => 'White', 3 => 'Outline', 4 => 'Dark'];

    public const DEFAULT_BUTTON_ON_LIGHT = '1';

    public const DEFAULT_BUTTON_ON_DARK = '2';

    /**
     * The original block button styles => button style slot, so pages saved before button styles keep their look.
     */
    public const LEGACY_BUTTON_STYLES = ['primary' => '1', 'secondary' => '2', 'outline' => '3'];

    /**
     * Each style's standard colours where they're fixed (slot 1 follows the site colour, and Outline
     * takes the text colour around it), for the readability check when only some colours are set.
     *
     * @var array<int, array<string, string>>
     */
    private const DEFAULT_BUTTON_COLORS = [
        2 => ['bg' => '#ffffff', 'bg_hover' => '#f3f4f6', 'text' => '#111827'],
        4 => ['bg' => '#111827', 'bg_hover' => '#1f2937', 'text' => '#ffffff'],
    ];

    public function __construct(private Branding $settings) {}

    /**
     * @return array<string>
     */
    public static function keys(): array
    {
        $keys = [
            self::BUTTON_CORNERS,
            self::CARD_CORNERS,
            self::IMAGE_CORNERS,
            self::INPUT_CORNERS,
            self::SHADOWS,
            self::HEADING_WEIGHT,
            self::HEADING_DECORATION,
            self::HEADING_DECORATION_COLOR,
            self::HEADING_DECORATION_DARK_COLOR,
            self::BUTTON_ON_LIGHT,
            self::BUTTON_ON_DARK,
        ];

        foreach (self::BUTTON_SLOTS as $slot) {
            foreach (array_keys(self::BUTTON_FIELDS) as $field) {
                $keys[] = self::buttonKey($slot, $field);
            }
        }

        return $keys;
    }

    public static function buttonKey(int $slot, string $field): string
    {
        return "branding.button_{$slot}_{$field}";
    }

    /**
     * @param  array<string, array{string, string}>  $options
     * @return array<string, string> key => label
     */
    public static function labels(array $options): array
    {
        return array_map(fn (array $option): string => __($option[0]), $options);
    }

    /**
     * The admin's name for each button style, as slot => name.
     *
     * @return array<int, string>
     */
    public function buttonNames(): array
    {
        $names = [];

        foreach (self::BUTTON_SLOTS as $slot) {
            $name = $this->settings->string(self::buttonKey($slot, 'name'));
            $names[$slot] = filled($name) ? $name : self::defaultButtonName($slot);
        }

        return $names;
    }

    public static function defaultButtonName(int $slot): string
    {
        $name = __(self::DEFAULT_BUTTON_NAMES[$slot]);

        // __() returns an array when the text matches a lang file name; fall back to the untranslated name.
        return is_string($name) ? $name : self::DEFAULT_BUTTON_NAMES[$slot];
    }

    /**
     * The button style slot ("1"–"4") a stored block button style means. "auto" (and anything unknown)
     * picks the light- or dark-background default.
     */
    public function buttonSlot(?string $style, bool $isOnDarkBackground): string
    {
        $style = self::LEGACY_BUTTON_STYLES[(string) $style] ?? $style;

        if (in_array((int) $style, self::BUTTON_SLOTS, true) && (string) (int) $style === $style) {
            return $style;
        }

        return $isOnDarkBackground
            ? $this->validSlot($this->settings->string(self::BUTTON_ON_DARK), self::DEFAULT_BUTTON_ON_DARK)
            : $this->validSlot($this->settings->string(self::BUTTON_ON_LIGHT), self::DEFAULT_BUTTON_ON_LIGHT);
    }

    /**
     * The button style ("1"–"4") chosen in a host app's own setting for one of its buttons. Not chosen: style 1.
     */
    public function siteButtonSlot(string $key): string
    {
        return $this->validSlot($this->settings->string($key), '1');
    }

    /**
     * The CSS for every saved style setting, or '' when nothing is saved.
     */
    public function css(): string
    {
        $variables = [];

        foreach ([
            '--radius-sb-button' => [self::BUTTON_CORNERS, self::BUTTON_CORNER_OPTIONS],
            '--radius-sb-card' => [self::CARD_CORNERS, self::CARD_CORNER_OPTIONS],
            '--radius-sb-image' => [self::IMAGE_CORNERS, self::IMAGE_CORNER_OPTIONS],
            '--radius-sb-input' => [self::INPUT_CORNERS, self::INPUT_CORNER_OPTIONS],
            '--shadow-sb-card' => [self::SHADOWS, self::SHADOW_OPTIONS],
            '--heading-weight' => [self::HEADING_WEIGHT, self::HEADING_WEIGHT_OPTIONS],
        ] as $variable => [$key, $options]) {
            $choice = $this->settings->string($key);

            if ($choice !== null && isset($options[$choice])) {
                $variables[] = "{$variable}: {$options[$choice][1]}";
            }
        }

        foreach (['--heading-decoration-color' => self::HEADING_DECORATION_COLOR, '--heading-decoration-color-dark' => self::HEADING_DECORATION_DARK_COLOR] as $variable => $key) {
            $color = $this->settings->string($key);

            if (Colour::isValidHex($color)) {
                $variables[] = "{$variable}: {$color}";
            }
        }

        $css = $variables === [] ? [] : [':root { '.implode('; ', $variables).'; }'];

        foreach (self::BUTTON_SLOTS as $slot) {
            if (($rule = $this->buttonCss($slot)) !== null) {
                $css[] = $rule;
            }
        }

        $decoration = $this->settings->string(self::HEADING_DECORATION);

        if ($decoration !== null && isset(self::DECORATION_CSS[$decoration])) {
            $css[] = self::DECORATION_CSS[$decoration];
        }

        return implode(' ', $css);
    }

    /**
     * Button styles whose text would be hard to read on their background, as their names. A colour
     * left blank is checked as its standard colour where that's fixed (styles 2 and 4).
     *
     * @param  array<string, mixed>  $values  field name (e.g. "button_2_text") => value, saved or unsaved form state
     * @return array<string>
     */
    public function unreadableButtonStyles(array $values): array
    {
        $unreadable = [];

        foreach (self::BUTTON_SLOTS as $slot) {
            $color = fn (string $field): ?string => Colour::isValidHex($values["button_{$slot}_{$field}"] ?? null)
                ? $values["button_{$slot}_{$field}"]
                : (self::DEFAULT_BUTTON_COLORS[$slot][$field] ?? null);

            $pairs = [
                [$color('text'), $color('bg')],
                [$color('text_hover') ?? $color('text'), $color('bg_hover') ?? $color('bg')],
            ];

            foreach ($pairs as [$text, $background]) {
                if ($text !== null && $background !== null && ! Colour::isReadableTextOn($text, $background)) {
                    $name = $values["button_{$slot}_name"] ?? null;
                    $unreadable[] = filled($name) ? (string) $name : self::defaultButtonName($slot);

                    break;
                }
            }
        }

        return $unreadable;
    }

    private function buttonCss(int $slot): ?string
    {
        $declarations = [];

        foreach (self::BUTTON_FIELDS as $field => $variable) {
            $color = $variable !== null ? $this->settings->string(self::buttonKey($slot, $field)) : null;

            if (Colour::isValidHex($color)) {
                $declarations[] = "{$variable}: {$color}";
            }
        }

        // A border colour on a style without one by default (1 and 4) needs a width to show.
        $hasBorder = Colour::isValidHex($this->settings->string(self::buttonKey($slot, 'border')));

        if ($hasBorder && in_array($slot, [1, 4], true)) {
            $declarations[] = '--site-button-border-width: 2px';
        }

        if ($this->settings->isOn(self::buttonKey($slot, 'uppercase'))) {
            $declarations[] = '--site-button-transform: uppercase';
            $declarations[] = '--site-button-tracking: 0.08em';
        }

        if ($this->settings->isOn(self::buttonKey($slot, 'lift'))) {
            $declarations[] = '--site-button-lift: -3px';
        }

        return $declarations === [] ? null : ".site-button-{$slot} { ".implode('; ', $declarations).'; }';
    }

    private function validSlot(?string $slot, string $default): string
    {
        return in_array((int) $slot, self::BUTTON_SLOTS, true) ? (string) (int) $slot : $default;
    }
}
