<?php

namespace ScBudgetWeb\SiteBuilder\Support;

/**
 * How a block shows a number (Stats, and the counters): with a thousands separator (9,000), plain (2040, for years) or
 * short (2m, 300k, 1.5bn), each with 0 to 3 decimal places. The count-up animation formats each step the same way
 * (JS), so the number never jumps when it lands.
 */
final class NumberFormat
{
    public const FORMATS = [
        'thousands' => 'With a thousands separator (9,000)',
        'plain' => 'Plain (2040, for a year)',
        'compact' => 'Short (2m, 300k)',
    ];

    public const MAX_DECIMALS = 3;

    /**
     * format() in JavaScript, (number, format, decimals) => text, for an Alpine component's own x-data: inline, so it
     * works in a block the visual builder adds without loading a script. Kept in step with format().
     */
    public const JS = "(n, f, d) => { const s = n < 0 ? '-' : ''; n = Math.abs(n); if (f === 'compact' && n >= 1e3) { const sizes = [[1e3, 'k'], [1e6, 'm'], [1e9, 'bn']]; let i = 2; while (i > 0 && n < sizes[i][0]) i--; while (i < 2 && Number((n / sizes[i][0]).toFixed(d)) >= 1e3) i++; const t = (n / sizes[i][0]).toFixed(d); return s + (t.includes('.') ? t.replace(/0+$/, '').replace(/\\.$/, '') : t) + sizes[i][1]; } const [w, x] = n.toFixed(d).split('.'); return s + (f === 'plain' ? w : w.replace(/\\B(?=(\\d{3})+(?!\\d))/g, ',')) + (x ? '.' + x : ''); }";

    /** Short format: from this size => its letter. */
    public const COMPACT = [1_000 => 'k', 1_000_000 => 'm', 1_000_000_000 => 'bn'];

    public static function format(int|float $number, mixed $format = 'thousands', mixed $decimals = 0): string
    {
        $format = self::formatName($format);
        $decimals = self::decimals($decimals);
        $sign = $number < 0 ? '-' : '';
        $number = abs($number);

        if ($format === 'compact' && $number >= 1_000) {
            $sizes = array_keys(self::COMPACT);
            $index = count($sizes) - 1;

            // The largest size it's at least, moving up when rounding reaches 1000 (999,999 is 1m, not 1000k).
            while ($index > 0 && $number < $sizes[$index]) {
                $index--;
            }

            while ($index < count($sizes) - 1 && round($number / $sizes[$index], $decimals) >= 1_000) {
                $index++;
            }

            $short = number_format($number / $sizes[$index], $decimals, '.', '');

            // 2.0m reads as 2m.
            return $sign.(str_contains($short, '.') ? rtrim(rtrim($short, '0'), '.') : $short).self::COMPACT[$sizes[$index]];
        }

        return $sign.number_format($number, $decimals, '.', $format === 'plain' ? '' : ',');
    }

    /**
     * The saved format if it's one of FORMATS, otherwise the standard one.
     */
    public static function formatName(mixed $format): string
    {
        return is_string($format) && isset(self::FORMATS[$format]) ? $format : 'thousands';
    }

    public static function decimals(mixed $decimals): int
    {
        return is_numeric($decimals) ? max(0, min(self::MAX_DECIMALS, (int) $decimals)) : 0;
    }
}
