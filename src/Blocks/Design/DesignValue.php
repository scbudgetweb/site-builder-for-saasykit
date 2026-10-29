<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Reading saved design values, every one checked again (a hand-edited value never reaches the page): lengths with a
 * unit from a list and within limits, choices from a whitelist, colours, and values that differ by screen size.
 *
 * A value that differs by screen size is saved as {desktop, tablet, phone}, each optional. It applies desktop first,
 *: the desktop value is used on every screen unless the tablet one is set (tablets and phones), and the
 * phone one wins on phones. Each screen size has its own class and property, added only when its value is set, so
 * nothing changes for a value that isn't:
 *
 *     .sb-d-{name}     --sb-{name}       every screen
 *     .sb-d-{name}-t   --sb-{name}-t     tablets and phones (up to 63.99rem)
 *     .sb-d-{name}-p   --sb-{name}-p     phones (up to 47.99rem)
 *     .sb-d-{name}-h   --sb-{name}-h     while the block is pointed at (resources/css/design/hover.css)
 */
final class DesignValue
{
    /** Screen size (or "hover", while the block is pointed at) => the suffix on its class and property. */
    public const DEVICES = ['desktop' => '', 'tablet' => '-t', 'phone' => '-p', 'hover' => '-h'];

    /**
     * A length such as "24px", "1.5rem" or "50%" (or a plain number, when '' is one of the units), tidied, or null when
     * it isn't one: a unit not in the list, or a number outside min to max.
     *
     * @param  array<int, string>  $units
     */
    public static function length(mixed $value, array $units, float $min, float $max): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        if (preg_match('/^\s*(-?\d+(?:\.\d+)?|-?\.\d+)\s*([a-z%]*)\s*$/i', (string) $value, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        if (! in_array($unit, $units, true) || $number < $min || $number > $max) {
            return null;
        }

        $tidy = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');

        return ($tidy === '-0' ? '0' : $tidy).$unit;
    }

    /**
     * A value for each screen size and for hover, from {desktop, tablet, phone, hover} (a plain value counts as desktop).
     *
     * @return array{desktop: mixed, tablet: mixed, phone: mixed, hover: mixed}
     */
    public static function devices(mixed $state): array
    {
        $state = is_array($state) ? $state : ['desktop' => $state];

        return ['desktop' => $state['desktop'] ?? null, 'tablet' => $state['tablet'] ?? null, 'phone' => $state['phone'] ?? null, 'hover' => $state['hover'] ?? null];
    }

    /**
     * Adds a length that can differ by screen size: .sb-d-{name}(-t|-p) and --sb-{name}(-t|-p) for each size that has
     * a valid value.
     *
     * @param  array<int, string>  $units
     */
    public static function addLength(DesignOutput $output, string $target, string $name, mixed $state, array $units, float $min, float $max): void
    {
        foreach (self::devices($state) as $device => $value) {
            $length = self::length($value, $units, $min, $max);

            if ($length !== null) {
                $suffix = self::DEVICES[$device];
                $output->addClass($target, "sb-d-{$name}{$suffix}")->setProperty($target, "--sb-{$name}{$suffix}", $length);
            }
        }
    }

    /**
     * The saved choice if it's one of the options, otherwise null (so nothing is added).
     *
     * @param  array<string, mixed>  $options
     */
    public static function choice(mixed $value, array $options): ?string
    {
        return (is_string($value) || is_int($value)) && array_key_exists((string) $value, $options) ? (string) $value : null;
    }

    /**
     * A colour as CSS: hex, with or without transparency, or a global colour's variable (Colour::css()).
     */
    public static function colour(mixed $value): ?string
    {
        return Colour::css($value);
    }

    /**
     * A whole number within limits, or null.
     */
    public static function integer(mixed $value, int $min, int $max): ?int
    {
        return is_numeric($value) && (int) $value == $value && (int) $value >= $min && (int) $value <= $max ? (int) $value : null;
    }
}
