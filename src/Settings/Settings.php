<?php

namespace ScBudgetWeb\SiteBuilder\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Models\Setting;
use ScBudgetWeb\SiteBuilder\Support\VersionedCache;

/**
 * The package's key/value settings, e.g. "branding.site_color" or "navigation.header".
 *
 * All rows are cached as one array, so a page reads the cache once however many settings it uses, under a version
 * that changes after each save (VersionedCache). Values can be strings, numbers, booleans or arrays. Callers must
 * tolerate a missing key: a cached copy can survive a deploy that adds new settings (lesson 9).
 */
class Settings
{
    /**
     * Every setting, once read in this request: a page reads settings a hundred times or more (Branding, colours,
     * presets), and on a shared host's file or database cache each cache read is a file or a query. The service is
     * scoped (SiteBuilderServiceProvider), so a queue job or an Octane request starts afresh.
     *
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    public function __construct(private Cache $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Every setting whose key starts with the prefix, e.g. "branding.".
     *
     * @return array<string, mixed>
     */
    public function startingWith(string $prefix): array
    {
        return array_filter($this->all(), fn (string $key): bool => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Saves the given settings. A null (or empty string) value removes the setting, so it falls back to its default.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                Setting::query()->where('key', $key)->delete();

                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->clearCache();
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function forget(array $keys): void
    {
        $this->set(array_fill_keys($keys, null));
    }

    /**
     * Also makes cached page HTML stale: blocks can depend on Branding and other settings.
     */
    public function clearCache(): void
    {
        $this->values = null;

        VersionedCache::newVersion($this->cache, $this->cacheKey().'.version');

        RenderCache::flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = VersionedCache::remember(
            $this->cache,
            $this->cacheKey(),
            $this->cacheKey().'.version',
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );

        return $this->values = is_array($values) ? $values : [];
    }

    private function cacheKey(): string
    {
        return (string) config('site-builder.cache_key', 'site-builder.settings');
    }
}
