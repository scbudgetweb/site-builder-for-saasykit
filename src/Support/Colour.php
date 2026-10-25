<?php

namespace ScBudgetWeb\SiteBuilder\Support;

use Filament\Support\Colors\Color;

/**
 * Colour helpers shared by Branding and the page blocks: validation, palettes, contrast and gradients.
 * Every colour that reaches CSS goes through isValidHex(), so admin input can never inject CSS.
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

    public static function isValidHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
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

    public static function isReadableTextOn(string $textHex, string $backgroundHex): bool
    {
        return Color::isTextContrastRatioAccessible($textHex, $backgroundHex);
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
     * The colour stops of a custom gradient (start, optional middle, end), or null without a valid start and end.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>|null
     */
    public static function gradientStops(array $values): ?array
    {
        $start = $values['gradient_start'] ?? null;
        $end = $values['gradient_end'] ?? null;

        if (! self::isValidHex($start) || ! self::isValidHex($end)) {
            return null;
        }

        $middle = $values['gradient_middle'] ?? null;

        return self::isValidHex($middle) ? [$start, $middle, $end] : [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $values  gradient_start, gradient_middle, gradient_end, gradient_direction
     */
    public static function gradientCss(array $values): ?string
    {
        $stops = self::gradientStops($values);

        if ($stops === null) {
            return null;
        }

        $direction = array_key_exists((string) ($values['gradient_direction'] ?? ''), self::GRADIENT_DIRECTIONS)
            ? (string) $values['gradient_direction']
            : self::DEFAULT_GRADIENT_DIRECTION;

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
