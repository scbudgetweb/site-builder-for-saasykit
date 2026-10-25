<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(config_path('site-builder.php'));

        parent::tearDown();
    }

    public function test_it_publishes_the_config_and_prints_the_lines_to_add(): void
    {
        $this->artisan('site-builder:install')
            ->expectsConfirmation('Run the database migrations now?', 'no')
            ->expectsOutputToContain('SiteBuilderPlugin::make()')
            ->expectsOutputToContain("@import '../../vendor/scbudgetweb/site-builder-for-saasykit/resources/css/site-builder.css';")
            ->expectsOutputToContain("@source '../../vendor/scbudgetweb/site-builder-for-saasykit/src';")
            ->expectsOutputToContain("@source '../../vendor/scbudgetweb/site-builder-for-saasykit/resources/views';")
            ->assertSuccessful();

        $this->assertFileExists(config_path('site-builder.php'));
    }

    public function test_the_settings_table_exists_after_migrating(): void
    {
        $this->assertTrue(Schema::hasTable('site_builder_settings'));
        $this->assertTrue(Schema::hasColumns('site_builder_settings', ['key', 'value']));
    }
}
