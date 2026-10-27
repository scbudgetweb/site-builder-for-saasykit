<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\VersionedCache;

/**
 * Cached block HTML for pages whose blocks are all cacheable (PageBlock::isCacheable()): no live figures, nothing
 * that depends on the visitor. Every cached page shares one version, which changes whenever a page, a library block
 * or any setting (Branding, Navigation, Legal pages) is saved, so nothing stale is ever shown. Entries also expire
 * after a day. Turn it off with config site-builder.pages.cache.
 */
class RenderCache
{
    private const VERSION_KEY = 'site-builder.render-version';

    public static function isEnabled(): bool
    {
        return (bool) config('site-builder.pages.cache', true);
    }

    /**
     * The page's cache key: the shared version, the page and when it was saved, and the language.
     */
    public static function key(Page $page): string
    {
        return implode('.', [
            'site-builder.page-html',
            self::version(),
            $page->getKey(),
            $page->updated_at?->getTimestamp() ?? 0,
            app()->getLocale(),
        ]);
    }

    /**
     * Makes every cached page stale (old entries are never read again and expire on their own), once the current
     * database transaction (if any) commits, so no render can read the new version and still see the old rows.
     */
    public static function flush(): void
    {
        VersionedCache::newVersion(cache()->store(), self::VERSION_KEY);
    }

    private static function version(): string
    {
        $version = cache()->get(self::VERSION_KEY);

        return is_string($version) ? $version : '0';
    }
}
