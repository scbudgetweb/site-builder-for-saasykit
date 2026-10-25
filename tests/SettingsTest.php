<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\DB;
use ScBudgetWeb\SiteBuilder\Models\Setting;
use ScBudgetWeb\SiteBuilder\Settings\Settings;

class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->app->make(Settings::class);
    }

    public function test_it_saves_and_reads_strings_numbers_booleans_and_arrays(): void
    {
        $this->settings->set([
            'branding.site_color' => '#2e6a4c',
            'branding.overlay_opacity' => 40,
            'branding.logo_in_panels' => true,
            'navigation.header' => [['type' => 'url', 'url' => '/about', 'label' => 'About']],
        ]);

        $this->assertSame('#2e6a4c', $this->settings->get('branding.site_color'));
        $this->assertSame(40, $this->settings->get('branding.overlay_opacity'));
        $this->assertTrue($this->settings->get('branding.logo_in_panels'));
        $this->assertSame('About', $this->settings->get('navigation.header')[0]['label']);
    }

    public function test_missing_settings_return_the_default(): void
    {
        $this->assertNull($this->settings->get('branding.site_color'));
        $this->assertSame('#000000', $this->settings->get('branding.site_color', '#000000'));
        $this->assertFalse($this->settings->has('branding.site_color'));
    }

    public function test_null_or_blank_removes_a_setting(): void
    {
        $this->settings->set(['branding.site_color' => '#2e6a4c', 'branding.secondary_color' => '#123456']);

        $this->settings->set(['branding.site_color' => null, 'branding.secondary_color' => '']);

        $this->assertSame(0, Setting::query()->count());
        $this->assertNull($this->settings->get('branding.site_color'));
    }

    public function test_forget_removes_settings(): void
    {
        $this->settings->set(['legal.privacy_policy' => '<p>Ours</p>']);

        $this->settings->forget(['legal.privacy_policy']);

        $this->assertFalse($this->settings->has('legal.privacy_policy'));
    }

    public function test_it_lists_settings_by_prefix(): void
    {
        $this->settings->set(['branding.site_color' => '#2e6a4c', 'navigation.footer' => ['tagline' => 'Hi']]);

        $this->assertSame(['branding.site_color' => '#2e6a4c'], $this->settings->startingWith('branding.'));
    }

    public function test_reads_come_from_one_cached_array_until_the_next_save(): void
    {
        $this->settings->set(['branding.site_color' => '#2e6a4c']);
        $this->settings->get('branding.site_color');

        // Changed behind the cache's back: still the cached value.
        DB::table('site_builder_settings')->update(['value' => json_encode('#ffffff')]);
        $this->assertSame('#2e6a4c', $this->settings->get('branding.site_color'));

        // Saving anything clears the cache.
        $this->settings->set(['branding.secondary_color' => '#123456']);
        $this->assertSame('#ffffff', $this->settings->get('branding.site_color'));
    }

    public function test_a_corrupt_cache_entry_reads_as_no_settings(): void
    {
        cache()->forever(config('site-builder.cache_key'), 'not an array');

        $this->assertSame([], $this->settings->all());
        $this->assertNull($this->settings->get('branding.site_color'));
    }
}
