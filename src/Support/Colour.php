<?php

namespace ScBudgetWeb\SiteBuilder\Support;

use Filament\Support\Colors\Color;
use ScBudgetWeb\SiteBuilder\Branding\Branding;

/**
 * Colour helpers shared by Branding and the page blocks: validation, palettes, contrast and gradients.
 * Every colour that reaches CSS goes through isValidHex() or, for a block's colours, css(), so admin input can never
 * inject CSS.
 */
class Colour
{
    /**
     * Gradient angle in degrees => label. (PHP stores these numeric keys as integers; lookups with the string form still match.)
     *
     * @var array<int, string>
     */
    public const GRADIENT_DIRECTIONS = [
        '135' => 'Diagonal, top left to bottom right',
        '90' => 'Left to right',
        '180' => 'Top to bottom',
        '45' => 'Diagonal, bottom left to top right',
    ];

    public const DEFAULT_GRADIENT_DIRECTION = '135';

    /** Gradient type => label. Linear (the default) runs at an angle; radial spreads out from a point. */
    public const GRADIENT_TYPES = [
        'linear' => 'Straight (at an angle)',
        'radial' => 'Round (from a point)',
        'conic' => 'Sweep (around a point)',
    ];

    /** Radial gradient shape => label. */
    public const GRADIENT_SHAPES = [
        'ellipse' => 'Oval (fits the area)',
        'circle' => 'Circle',
    ];

    /** A point in an area (a radial gradient's centre, a background image's position) => [label, CSS position]. */
    public const AREA_POSITIONS = [
        'center' => ['Centre', 'center'],
        'top' => ['Top', 'top'],
        'bottom' => ['Bottom', 'bottom'],
        'left' => ['Left', 'left'],
        'right' => ['Right', 'right'],
        'top-left' => ['Top left', 'top left'],
        'top-right' => ['Top right', 'top right'],
        'bottom-left' => ['Bottom left', 'bottom left'],
        'bottom-right' => ['Bottom right', 'bottom right'],
    ];

    /**
     * @var array<string, string>
     */
    public const IMAGE_POSITIONS = [
        'center' => 'Centre',
        'top' => 'Top',
        'bottom' => 'Bottom',
    ];

    public const DEFAULT_OVERLAY_COLOR = '#000000';

    public const DEFAULT_OVERLAY_OPACITY = 50;

    /** Below this overlay opacity, text on an image is flagged as possibly hard to read. */
    public const MIN_READABLE_OVERLAY_OPACITY = 40;

    /** A block's reference to a global colour (Branding › Colours): "gc:" and the colour's id. */
    public const REFERENCE_PREFIX = 'gc:';

    /** The built-in global colours, always offered: id => [label, CSS variable]. */
    public const BUILT_IN_GLOBALS = [
        'primary' => ['Site colour', '--color-primary-500'],
        'secondary' => ['Site secondary colour', '--color-secondary-500'],
    ];

    public const GLOBAL_ID_PATTERN = '/^[a-z0-9]{1,24}$/';

    public static function isValidHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * A hex colour, optionally with transparency: #rrggbb or #rrggbbaa.
     */
    public static function isValidHexWithAlpha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $value) === 1;
    }

    /**
     * A well-formed reference to a global colour ("gc:primary", "gc:k3x9a2"), whether or not it still exists.
     */
    public static function isReference(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::REFERENCE_PREFIX)
            && preg_match(self::GLOBAL_ID_PATTERN, substr($value, strlen(self::REFERENCE_PREFIX))) === 1;
    }

    /**
     * What a block's colour field may hold: a hex colour (with or without transparency) or a global colour reference.
     */
    public static function isValidBlockColour(mixed $value): bool
    {
        return self::isValidHexWithAlpha($value) || self::isReference($value);
    }

    /**
     * A block colour as CSS, or null when it isn't one. A global colour becomes its CSS variable, so changing it in
     * Branding changes every block that uses it (a deleted one leaves the variable unset, so the standard look shows).
     */
    public static function css(mixed $value): ?string
    {
        if (self::isValidHexWithAlpha($value)) {
            return $value;
        }

        if (! self::isReference($value)) {
            return null;
        }

        $id = substr($value, strlen(self::REFERENCE_PREFIX));

        return 'var('.(self::BUILT_IN_GLOBALS[$id][1] ?? "--sb-gc-{$id}").')';
    }

    /**
     * A block colour as plain #rrggbb, for working out contrast and shades (transparency is ignored; a global colour
     * is looked up in Branding), or null when it isn't one or the global colour isn't set.
     */
    public static function hex(mixed $value): ?string
    {
        if (self::isValidHexWithAlpha($value)) {
            return strtolower(substr($value, 0, 7));
        }

        if (! self::isReference($value)) {
            return null;
        }

        $colour = app(Branding::class)->globalColours()[substr($value, strlen(self::REFERENCE_PREFIX))]['colour'] ?? null;

        return self::isValidHexWithAlpha($colour) ? strtolower(substr($colour, 0, 7)) : null;
    }

    /**
     * A block colour at an opacity (0–1): rgba() for a hex colour, as before, and a colour mix for a global one.
     */
    public static function withOpacity(string $value, float $opacity): string
    {
        $alpha = rtrim(rtrim(number_format($opacity, 2, '.', ''), '0'), '.') ?: '0';

        if (self::isValidHexWithAlpha($value)) {
            [$red, $green, $blue] = sscanf($value, '#%02x%02x%02x');

            return "rgba({$red}, {$green}, {$blue}, {$alpha})";
        }

        return 'color-mix(in srgb, '.(self::css($value) ?? 'transparent').' '.round($opacity * 100, 2).'%, transparent)';
    }

    /**
     * Shades 50–950 with the chosen colour exactly at 500. Filament's default generator normalises
     * lightness, which loses the exact brand colour (a brand black came out mid-grey).
     *
     * @return array<int, string>
     */
    public static function palette(string $hex): array
    {
        return Color::generateV3Palette($hex);
    }

    /**
     * Either colour may be a block colour (with transparency, or a global colour); one that can't be worked out counts
     * as readable, so nothing is flagged or changed for it.
     */
    public static function isReadableTextOn(string $textHex, string $backgroundHex): bool
    {
        $text = self::hex($textHex);
        $background = self::hex($backgroundHex);

        return $text === null || $background === null || Color::isTextContrastRatioAccessible($text, $background);
    }

    /**
     * Buttons use shade 600 with white text, so that's the pairing checked against WCAG AA.
     */
    public static function hasAccessibleButtonContrast(string $hex): bool
    {
        return self::isReadableTextOn(Color::convertToHex(self::palette($hex)[600]), '#ffffff');
    }

    public static function shade(string $hex, int $shade): string
    {
        return Color::convertToHex(self::palette($hex)[$shade]);
    }

    /**
     * The colour stops of a custom gradient (start, optional middle, end) as CSS, or null without a valid start and
     * end. A block's stops may be see-through or global colours (css()).
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>|null
     */
    public static function gradientStops(array $values): ?array
    {
        $start = self::css($values['gradient_start'] ?? null);
        $end = self::css($values['gradient_end'] ?? null);

        if ($start === null || $end === null) {
            return null;
        }

        $middle = self::css($values['gradient_middle'] ?? null);

        return $middle !== null ? [$start, $middle, $end] : [$start, $end];
    }

    /**
     * A block's gradient stops (the raw saved values, not the CSS), for working out contrast.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    public static function gradientStopValues(array $values): array
    {
        return array_values(array_filter(
            [$values['gradient_start'] ?? null, $values['gradient_middle'] ?? null, $values['gradient_end'] ?? null],
            fn (mixed $value): bool => self::isValidBlockColour($value),
        ));
    }

    /**
     * A linear gradient at gradient_direction, with gradient_type "radial" one spreading from gradient_position in
     * gradient_shape, or with "conic" one sweeping round gradient_position, starting at gradient_direction. Values without a type (saved before radial gradients, and Branding's banners) stay linear.
     *
     * @param  array<string, mixed>  $values  gradient_start, gradient_middle, gradient_end, gradient_direction,
     *                                        gradient_type, gradient_shape, gradient_position
     */
    public static function gradientCss(array $values): ?string
    {
        $stops = self::gradientStops($values);

        if ($stops === null) {
            return null;
        }

        if (($values['gradient_type'] ?? null) === 'radial') {
            $shape = array_key_exists((string) ($values['gradient_shape'] ?? ''), self::GRADIENT_SHAPES) ? (string) $values['gradient_shape'] : 'ellipse';
            $position = self::AREA_POSITIONS[(string) ($values['gradient_position'] ?? '')][1] ?? 'center';

            return "radial-gradient({$shape} at {$position}, ".implode(', ', $stops).')';
        }

        $direction = array_key_exists((string) ($values['gradient_direction'] ?? ''), self::GRADIENT_DIRECTIONS)
            ? (string) $values['gradient_direction']
            : self::DEFAULT_GRADIENT_DIRECTION;

        if (($values['gradient_type'] ?? null) === 'conic') {
            $position = self::AREA_POSITIONS[(string) ($values['gradient_position'] ?? '')][1] ?? 'center';

            return "conic-gradient(from {$direction}deg at {$position}, ".implode(', ', $stops).')';
        }

        return "linear-gradient({$direction}deg, ".implode(', ', $stops).')';
    }

    public static function overlayOpacity(mixed $opacity): int
    {
        return is_numeric($opacity) ? max(0, min(100, (int) $opacity)) : self::DEFAULT_OVERLAY_OPACITY;
    }

    /**
     * @param  array<string, string>  $options  key => label
     * @return array<string, string>
     */
    public static function translated(array $options): array
    {
        return array_map(fn (string $label): string => __($label), $options);
    }
}
