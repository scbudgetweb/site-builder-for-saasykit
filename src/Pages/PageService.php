<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\SiteBuilder;
use ScBudgetWeb\SiteBuilder\Support\VersionedCache;
use Throwable;

/**
 * Lookups for published pages. Every request to a built-in page that can be replaced (a slot) and every unknown
 * URL asks "is there a page for this?", so the answer is cached as a small slug/slot => id map, cleared whenever a
 * page is saved, deleted or restored.
 */
class PageService
{
    public const UPLOAD_SUBFOLDER = 'pages';

    private const CACHE_KEY = 'site-builder.published-pages';

    private const LINKS_CACHE_KEY = 'site-builder.page-links';

    /** Changes after every page save, delete or restore, so the published map and links above are read afresh. */
    private const VERSION_KEY = 'site-builder.pages-version';

    public function __construct(private Router $router, private SiteBuilder $siteBuilder) {}

    /**
     * The published page replacing this built-in page, if any. Never throws, because the built-in page must still
     * show before the package is migrated.
     */
    public function forSlot(string $slot): ?Page
    {
        try {
            $id = $this->publishedMap()['slots'][$slot] ?? null;

            return $id ? Page::query()->published()->find($id) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        $id = $this->publishedMap()['slugs'][Str::lower($slug)] ?? null;

        return $id ? Page::query()->published()->find($id) : null;
    }

    public function clearCache(): void
    {
        VersionedCache::newVersion(cache()->store(), self::VERSION_KEY);
    }

    /**
     * Published pages as id => [title, url], for menu links. A draft or binned page isn't in the map, so menus skip
     * it; a page replacing a built-in page links to that page's URL.
     *
     * @return array<int, array{title: string, url: string}>
     */
    public function publishedPageLinks(): array
    {
        $pages = VersionedCache::remember(cache()->store(), self::LINKS_CACHE_KEY, self::VERSION_KEY, fn (): array => Page::query()
            ->published()
            ->get(['id', 'title', 'slug', 'replaces'])
            ->mapWithKeys(fn (Page $page): array => [$page->id => [
                'title' => $page->title,
                'slug' => $page->slug,
                'replaces' => $page->replaces,
            ]])
            ->all());

        // URLs are built per request rather than cached, so they always use the current host.
        return array_map(fn (array $page): array => [
            'title' => (string) ($page['title'] ?? ''),
            'url' => $this->siteBuilder->slot($page['replaces'] ?? null)?->url() ?? url((string) ($page['slug'] ?? '')),
        ], array_filter($pages, 'is_array'));
    }

    /**
     * Slugs a page can't use because the URL already belongs to something else: the first segment of every
     * registered route (blog, admin, dashboard, api…) and every top-level file or folder in public/ (build, fonts,
     * robots.txt…), which the web server serves before Laravel.
     *
     * @return array<int, string>
     */
    public function reservedSlugs(): array
    {
        $fromRoutes = collect($this->router->getRoutes()->getRoutes())
            ->reject(fn (Route $route): bool => $route->isFallback)
            ->map(fn (Route $route): string => Str::lower(Str::before(ltrim($route->uri(), '/'), '/')))
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'));

        $fromPublic = collect(File::glob(public_path('*')) ?: [])->map(fn (string $path): string => Str::lower(basename($path)));

        return $fromRoutes->merge($fromPublic)->unique()->sort()->values()->all();
    }

    public function uploadDisk(): string
    {
        return $this->siteBuilder->uploadDisk();
    }

    /**
     * The folder block images and share images are uploaded to (site-builder/pages by default).
     */
    public function uploadDirectory(): string
    {
        return $this->siteBuilder->uploadDirectory(self::UPLOAD_SUBFOLDER);
    }

    /**
     * Whether a stored file path is one of the page uploads (and not, say, a Branding logo or a path with "..").
     */
    public function isPageUpload(mixed $path): bool
    {
        return is_string($path) && str_starts_with($path, $this->uploadDirectory().'/') && ! str_contains($path, '..');
    }

    /**
     * Paths of the files uploaded into blocks (anything stored in the pages upload folder).
     *
     * @param  array<mixed>  $blocks
     * @return array<int, string>
     */
    public function uploadedPaths(array $blocks): array
    {
        $paths = [];

        array_walk_recursive($blocks, function (mixed $value) use (&$paths): void {
            if ($this->isPageUpload($value)) {
                $paths[] = $value;
            }
        });

        return array_values(array_unique($paths));
    }

    /**
     * Deletes files removed from a page or library block on save, unless another page (including one in the bin,
     * which can be restored), a library block or a kept revision (which can be restored too) still uses them, e.g.
     * after Duplicate or Save to library.
     *
     * The match is a plain LIKE with no escape characters, which works the same on SQLite and MySQL (lesson 13).
     * An "_" in a file name matches any character, which can only keep a file, never delete one that's in use.
     *
     * @param  array<int, string>  $removedPaths
     */
    public function deleteUnusedUploads(array $removedPaths, ?Page $page = null, ?SavedBlock $exceptSavedBlock = null): void
    {
        foreach ($removedPaths as $path) {
            if (! $this->isUploadInUse($path, $page, $exceptSavedBlock)) {
                Storage::disk($this->uploadDisk())->delete($path);
            }
        }
    }

    /**
     * Whether a page (including one in the bin), a library block, a kept revision or a visual builder draft uses the
     * upload, leaving out the page or library block being changed.
     */
    public function isUploadInUse(string $path, ?Page $exceptPage = null, ?SavedBlock $exceptSavedBlock = null): bool
    {
        // Encoded the way the models store their JSON (Page::asJson()), so the path is found as it was saved.
        $pattern = '%'.json_encode($path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'%';

        return Page::withTrashed()
            ->when($exceptPage, fn ($query) => $query->whereKeyNot($exceptPage->getKey()))
            ->where(fn ($query) => $query
                ->where('blocks', 'like', $pattern)
                ->orWhere('share_image', $path))
            ->exists()
            || SavedBlock::query()
                ->when($exceptSavedBlock, fn ($query) => $query->whereKeyNot($exceptSavedBlock->getKey()))
                ->where('blocks', 'like', $pattern)
                ->exists()
            || PageRevision::query()->where('snapshot', 'like', $pattern)->exists()
            // A visual builder draft, including what its undo and redo could bring back.
            || PageDraft::query()->where(fn ($query) => $query
                ->where('blocks', 'like', $pattern)
                ->orWhere('history', 'like', $pattern)
                ->orWhere('future', 'like', $pattern))
                ->exists();
    }

    /**
     * Never trusts the cache's shape (lesson 9): anything unexpected reads as no pages.
     *
     * @return array{slots: array<string, int>, slugs: array<string, int>}
     */
    private function publishedMap(): array
    {
        $map = VersionedCache::remember(cache()->store(), self::CACHE_KEY, self::VERSION_KEY, function (): array {
            $pages = Page::query()->published()->get(['id', 'slug', 'replaces']);

            return [
                'slots' => $pages->filter(fn (Page $page): bool => $page->replaces !== null)
                    ->mapWithKeys(fn (Page $page): array => [$page->replaces => $page->id])
                    ->all(),
                'slugs' => $pages->mapWithKeys(fn (Page $page): array => [Str::lower($page->slug) => $page->id])->all(),
            ];
        });

        return [
            'slots' => is_array($map['slots'] ?? null) ? $map['slots'] : [],
            'slugs' => is_array($map['slugs'] ?? null) ? $map['slugs'] : [],
        ];
    }
}
