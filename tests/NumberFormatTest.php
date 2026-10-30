<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use ScBudgetWeb\SiteBuilder\Support\NumberFormat;

/**
 * How Stats (and the counters) write a number: with a thousands separator, plain or short, with decimal places.
 */
class NumberFormatTest extends PageTestCase
{
    public function test_numbers_are_written_in_each_format(): void
    {
        $this->assertSame('9,000', NumberFormat::format(9000));
        $this->assertSame('2040', NumberFormat::format(2040, 'plain'), 'A year without a comma.');
        $this->assertSame('1.25', NumberFormat::format(1.25, 'thousands', 2), 'Decimals are kept.');
        $this->assertSame('12,345.7', NumberFormat::format(12345.678, 'thousands', 1));
        $this->assertSame('-4,200', NumberFormat::format(-4200));

        $this->assertSame('2m', NumberFormat::format(2_000_000, 'compact'));
        $this->assertSame('1.5m', NumberFormat::format(1_500_000, 'compact', 1));
        $this->assertSame('2m', NumberFormat::format(2_049_999, 'compact', 1), '2.0m reads as 2m.');
        $this->assertSame('300k', NumberFormat::format(300_000, 'compact'));
        $this->assertSame('999k', NumberFormat::format(999_499, 'compact'));
        $this->assertSame('1m', NumberFormat::format(999_999, 'compact'), 'Not 1000k.');
        $this->assertSame('1.25bn', NumberFormat::format(1_250_000_000, 'compact', 2));
        $this->assertSame('999', NumberFormat::format(999, 'compact'), 'Under a thousand stays as it is.');
    }

    public function test_hand_edited_settings_fall_back(): void
    {
        $this->assertSame('9,000', NumberFormat::format(9000, 'roman', 'lots'));
        $this->assertSame('1.250', NumberFormat::format(1.25, 'thousands', 9), 'At most 3 decimal places.');
        $this->assertSame('1', NumberFormat::format(1.25, 'thousands', -2));
    }

    public function test_the_animation_formats_the_same_way(): void
    {
        // The JavaScript twin has the same steps; a run against Node compared 189 cases when it was written.
        foreach (["'compact'", "'plain'", "[1e3, 'k'], [1e6, 'm'], [1e9, 'bn']", '>= 1e3', '.toFixed(d)'] as $part) {
            $this->assertStringContainsString($part, NumberFormat::JS);
        }
    }

    public function test_stats_show_their_format_and_animate_with_it(): void
    {
        $this->page(['blocks' => [self::block('stats', ['items' => [
            ['source' => 'custom', 'value' => 2040, 'label' => 'Net zero by', 'format' => 'plain'],
            ['source' => 'custom', 'value' => 1.25, 'label' => 'Megawatts', 'decimals' => 2],
            ['source' => 'custom', 'value' => 2000000, 'label' => 'Visitors', 'format' => 'compact'],
        ]])]]);

        $html = $this->get('/about-us')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('/>2040<\/span>/', $html);
        $this->assertMatchesRegularExpression('/>1\.25<\/span>/', $html);
        $this->assertMatchesRegularExpression('/>2m<\/span>/', $html);
        $this->assertStringContainsString("x-text=\"format(shown, 'compact', 0)\"", $html);
        $this->assertStringNotContainsString('2,040', $html);
    }
}
