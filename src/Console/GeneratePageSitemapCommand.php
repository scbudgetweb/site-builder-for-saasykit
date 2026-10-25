<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Writes public/sitemap-pages.xml with every published, indexable page at /{slug}, and makes sure robots.txt points at
 * it. Pages that replace a built-in page are left out: that page's own URL is in the app's sitemap already.
 * Schedule it after SaaSykit's app:generate-sitemap, which rewrites robots.txt.
 */
class GeneratePageSitemapCommand extends Command implements Isolatable
{
    public const FILE_NAME = 'sitemap-pages.xml';

    protected $signature = 'site-builder:sitemap {--force : Generate it outside production too}';

    protected $description = 'Generate sitemap-pages.xml for the published site builder pages and add it to robots.txt';

    public function handle(): int
    {
        if (! $this->option('force') && ! app()->environment('production')) {
            $this->warn('Not generating the page sitemap outside production (use --force to generate it anyway).');

            return self::SUCCESS;
        }

        $entries = [];

        Page::query()
            ->published()
            ->where('noindex', false)
            ->whereNull('replaces')
            ->select(['id', 'slug', 'replaces', 'updated_at'])
            ->chunkById(500, function ($pages) use (&$entries): void {
                foreach ($pages as $page) {
                    $entries[] = '  <url>'
                        .'<loc>'.e($page->url()).'</loc>'
                        .($page->updated_at ? '<lastmod>'.$page->updated_at->toAtomString().'</lastmod>' : '')
                        .'</url>';
                }
            });

        file_put_contents(public_path(self::FILE_NAME), '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries).($entries === [] ? '' : "\n")
            .'</urlset>'."\n");

        $robotsPath = public_path('robots.txt');
        $sitemapLine = 'Sitemap: '.url(self::FILE_NAME);
        $robots = is_file($robotsPath) ? (string) file_get_contents($robotsPath) : "User-agent: *\nDisallow:\n";

        if (! str_contains($robots, $sitemapLine)) {
            file_put_contents($robotsPath, rtrim($robots)."\n\n".$sitemapLine."\n");
        }

        $this->info(trans_choice('Page sitemap written with :count page.|Page sitemap written with :count pages.', count($entries), ['count' => count($entries)]));

        return self::SUCCESS;
    }
}
