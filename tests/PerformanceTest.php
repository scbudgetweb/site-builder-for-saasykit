<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ScBudgetWeb\SiteBuilder\Blocks\BlockDesign;
use ScBudgetWeb\SiteBuilder\Blocks\BlockPresets;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

/**
 * What keeps a page quick on a shared host, where the cache is files or the database and every cache read costs a
 * file or a query: settings are read from the cache once a request, and a cached page view reads only a handful of
 * cache entries and one query, presets or not.
 */
class PerformanceTest extends PageTestCase
{
    /** @var array<int, string> */
    private array $cacheReads = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen([CacheHit::class, CacheMissed::class], function (CacheHit|CacheMissed $event): void {
            $this->cacheReads[] = $event->key;
        });
    }

    public function test_settings_are_read_from_the_cache_once_a_request_and_a_save_is_seen_straight_away(): void
    {
        $settings = app(Settings::class);
        $settings->set(['branding.site_name' => 'Village Hall']);
        $this->cacheReads = [];

        foreach (range(1, 50) as $ignored) {
            app(Branding::class)->string('branding.site_name');
        }

        $this->assertCount(2, $this->cacheReads, 'The version and the settings, once.');

        $settings->set(['branding.site_name' => 'Sometown Hall']);
        $this->assertSame('Sometown Hall', app(Branding::class)->string('branding.site_name'));

        $this->app->forgetScopedInstances();
        $this->assertNotSame($settings, app(Settings::class), 'A new request (or queue job) starts afresh.');
    }

    public function test_a_cached_page_view_reads_a_handful_of_cache_entries_and_one_query_with_presets(): void
    {
        $blocks = [];
        foreach (range(1, 5) as $number) {
            $blocks[] = self::block('image_text', ['heading' => "Village Hall {$number}", 'buttons' => [['label' => 'Book', 'url' => '/book']]]);
            $blocks[] = self::block('section', ['rows' => [['layout' => '2', 'columns' => [
                ['blocks' => [self::block('text', ['content' => '<p>Sometown</p>'])]],
                ['blocks' => [self::block('call_to_action', ['heading' => 'Book'])]],
            ]]]]);
        }
        $this->page(['blocks' => $blocks]);
        app(BlockPresets::class)->create('image_text', 'Roomy', ['appearance' => ['spacing' => 'large']], isDefault: true);

        $this->get('/about-us')->assertOk();

        $this->cacheReads = [];
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/about-us')->assertOk()->assertSee('Village Hall 5');

        $this->assertLessThanOrEqual(8, count($this->cacheReads), implode(', ', $this->cacheReads));
        $this->assertCount(1, DB::getQueryLog(), 'Only the page itself.');
    }

    public function test_a_pages_fonts_are_kept_with_its_cached_html_and_a_save_makes_them_stale(): void
    {
        $blocks = [self::block('text', ['content' => '<p>Hi</p>', 'design' => ['typography' => ['heading' => ['font' => 'inter']]]])];

        BlockDesign::fontStylesheets($blocks);
        $this->cacheReads = [];
        BlockDesign::fontStylesheets($blocks);
        $this->assertContains(true, array_map(fn (string $key): bool => str_starts_with($key, 'site-builder.block-data.fonts.'), $this->cacheReads));

        $fontEntries = fn (): array => array_values(array_filter($this->cacheReads, fn (string $key): bool => str_starts_with($key, 'site-builder.block-data.fonts.')));
        $before = $fontEntries();

        app(Settings::class)->set(['branding.site_name' => 'Village Hall']);
        $this->cacheReads = [];
        BlockDesign::fontStylesheets($blocks);

        $this->assertNotSame($before, $fontEntries(), 'Saving a setting moves fonts to a new version.');
    }
}
