<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Support\Facades\File;
use ScBudgetWeb\SiteBuilder\Console\GeneratePageSitemapCommand;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;

class PageSitemapTest extends PageTestCase
{
    protected function tearDown(): void
    {
        File::delete([public_path(GeneratePageSitemapCommand::FILE_NAME), public_path('robots.txt')]);

        parent::tearDown();
    }

    public function test_it_lists_published_indexable_pages_and_adds_itself_to_robots_txt(): void
    {
        File::put(public_path('robots.txt'), "User-agent: *\nDisallow:\n\nSitemap: http://localhost/sitemap.xml\n");

        $this->page();
        $this->page(['slug' => 'draft', 'status' => PageStatus::Draft]);
        $this->page(['slug' => 'hidden', 'noindex' => true]);
        $this->page(['slug' => 'new-home', 'replaces' => 'home']);

        $this->artisan('site-builder:sitemap', ['--force' => true])->assertSuccessful();

        $sitemap = File::get(public_path(GeneratePageSitemapCommand::FILE_NAME));
        $this->assertStringContainsString('<loc>http://localhost/about-us</loc>', $sitemap);
        $this->assertStringNotContainsString('draft', $sitemap);
        $this->assertStringNotContainsString('hidden', $sitemap);
        $this->assertStringNotContainsString('new-home', $sitemap);

        $robots = File::get(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);
        $this->assertSame(1, substr_count($robots, 'Sitemap: http://localhost/sitemap-pages.xml'));

        $this->artisan('site-builder:sitemap', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, substr_count(File::get(public_path('robots.txt')), 'sitemap-pages.xml'), 'Running it again doesn\'t add the line twice.');
    }

    public function test_it_does_nothing_outside_production_unless_forced(): void
    {
        $this->artisan('site-builder:sitemap')->assertSuccessful();

        $this->assertFileDoesNotExist(public_path(GeneratePageSitemapCommand::FILE_NAME));
    }
}
