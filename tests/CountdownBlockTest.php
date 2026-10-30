<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Blocks\Core\CountdownBlock;
use ScBudgetWeb\SiteBuilder\Blocks\PageRenderer;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * The Countdown block: days, hours, minutes and seconds to a moment in the site's time zone, counted in the browser
 * from a timestamp (so a cached page counts right), and what happens when it ends.
 */
class CountdownBlockTest extends PageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/London']);
        // 12:00 UTC is 13:00 in London in summer.
        Carbon::setTestNow(CarbonImmutable::parse('2026-07-01 12:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-01 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<int, array{0: string, 1: string}> each unit's number and label as drawn
     */
    private static function units(string $html): array
    {
        preg_match_all('/<span class="sb-item-value[^"]*"\s*x-text="[^"]*">([^<]*)<\/span>\s*<span class="sb-item-label[^"]*"\s*x-text="[^"]*">([^<]*)<\/span>/', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [$match[1], $match[2]], $matches);
    }

    public function test_it_counts_down_to_a_moment_in_the_sites_time_zone(): void
    {
        $this->page(['blocks' => [self::block('countdown', ['heading' => 'Open day', 'ends_at' => '2026-07-02 15:03:04'])]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame([['1', 'Day'], ['02', 'Hours'], ['03', 'Minutes'], ['04', 'Seconds']], self::units($html), '1 day, 2 hours, 3 minutes and 4 seconds from 13:00 London time.');
        $this->assertStringContainsString('end: '.(CarbonImmutable::parse('2026-07-02 14:03:04', 'UTC')->getTimestamp() * 1000).',', $html, 'The browser counts to the moment itself.');
        $this->assertStringContainsString('Ends 2 July 2026, 15:03', $html);
        $this->assertStringContainsString('Open day', $html);
        $this->assertMatchesRegularExpression('/x-show="ended"\s*style="display: none"\s*>This has now ended.<\/p>/', $html, 'The message waits, until the browser sees it end.');
    }

    public function test_seconds_can_be_left_out(): void
    {
        $this->page(['blocks' => [self::block('countdown', ['ends_at' => '2026-07-01 14:00:00', 'show_seconds' => false])]]);

        $this->assertSame([['0', 'Days'], ['01', 'Hour'], ['00', 'Minutes']], self::units($this->get('/about-us')->getContent()));
    }

    public function test_when_it_has_ended(): void
    {
        $this->page(['blocks' => [
            self::block('countdown', ['heading' => 'Spring fair', 'ends_at' => '2026-06-01 10:00:00', 'ended_message' => 'See you next year <3']),
            self::block('countdown', ['heading' => 'Summer fair', 'ends_at' => '2026-06-01 10:00:00', 'when_ended' => 'hide']),
            self::block('countdown', ['heading' => 'Autumn fair', 'ends_at' => '2026-06-01 10:00:00', 'when_ended' => 'zero']),
            self::block('countdown', ['heading' => 'Winter fair']),
        ]]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="sb-items[^"]*"\s*x-show="! ended"\s*style="display: none"/', $html, 'The numbers make way for the message: an inline style, which x-show takes over.');
        $this->assertMatchesRegularExpression('/x-show="ended"\s*>See you next year &lt;3<\/p>/', $html);
        $this->assertStringNotContainsString('Summer fair', $html, 'Hidden once ended.');
        $this->assertStringContainsString('Autumn fair', $html);
        $this->assertContains(['0', 'Days'], self::units($html));
        $this->assertStringNotContainsString('Winter fair', $html, 'Nothing to count down to.');
    }

    public function test_the_hide_is_left_to_the_browser_only_off_the_canvas(): void
    {
        $page = $this->page(['blocks' => [self::block('countdown', ['ends_at' => '2026-07-02 15:00:00', 'when_ended' => 'hide'])]]);

        $this->assertStringContainsString('if (this.ended && true)', $this->get('/about-us')->getContent());
        $this->assertStringContainsString('if (this.ended && false)', implode('', app(PageRenderer::class)->renderBlocks($page->blocks, $page, marksNodes: true)), 'It stays on the canvas, to edit.');
    }

    public function test_unit_values(): void
    {
        $left = 3 * 86400 + 23 * 3600 + 59 * 60 + 5;

        $this->assertSame(['3', '23', '59', '05'], array_map(fn (string $unit): string => CountdownBlock::unitValue($left, $unit), array_keys(CountdownBlock::UNITS)));
        $this->assertSame(['0', '00', '00', '00'], array_map(fn (string $unit): string => CountdownBlock::unitValue(0, $unit), array_keys(CountdownBlock::UNITS)));
    }

    public function test_it_needs_a_moment_to_count_to(): void
    {
        $result = app(PageImport::class)->import(['title' => 'Open day', 'slug' => 'open-day', 'blocks' => [
            ['type' => 'countdown', 'data' => ['heading' => 'Open day', 'when_ended' => 'explode']],
        ]]);

        $this->assertSame([
            'blocks.0.data.ends_at: The counts down to field is required.',
            'blocks.0.data.when_ended: The selected when it ends is invalid.',
        ], $result->errors);
    }
}
