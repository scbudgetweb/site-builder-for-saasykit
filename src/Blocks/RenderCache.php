<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Closure;
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

    /**
     * A Theme Builder template's cache key: the shared version (so a page, library, Branding or Navigation save, which
     * a menu or logo shows, makes it stale), the template and when it was saved, and the language.
     */
    public static function templateKey(int $id, int $updatedAt): string
    {
        return implode('.', ['site-builder.template-html', self::version(), $id, $updatedAt, app()->getLocale()]);
    }

    /**
     * Something worked out from blocks (e.g. their fonts), kept under the shared version so any save makes it stale.
     * Keyed by the blocks themselves, so a preview or a draft never shares a page's entry. Worked out every time when
     * the cache is off.
     *
     * @param  array<int|string, mixed>  $blocks
     * @param  Closure(): array<int|string, mixed>  $callback
     * @return array<int|string, mixed>
     */
    public static function remember(string $name, array $blocks, Closure $callback): array
    {
        if (! self::isEnabled()) {
            return $callback();
        }

        $value = cache()->remember(
            implode('.', ['site-builder.block-data', $name, self::version(), md5((string) json_encode($blocks)), app()->getLocale()]),
            now()->addDay(),
            $callback,
        );

        return is_array($value) ? $value : $callback();
    }

    private static function version(): string
    {
        $version = cache()->get(self::VERSION_KEY);

        return is_string($version) ? $version : '0';
    }
}
