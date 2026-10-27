<?php

namespace ScBudgetWeb\SiteBuilder\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A cached copy of database data, stored under a version that changes after every save. Forgetting a key isn't enough:
 * a request that read the database just before a save could store the old data again straight after it. With a
 * version, that request stores it under the old version, which nothing reads any more.
 *
 * The version changes once the save's database transaction commits, so no request can read the new version and still
 * see the old rows. Copies left under old versions expire on their own.
 */
final class VersionedCache
{
    private const TTL_DAYS = 7;

    public static function remember(Repository $cache, string $key, string $versionKey, Closure $callback): mixed
    {
        $version = $cache->get($versionKey);

        return $cache->remember($key.'.'.(is_string($version) ? $version : '0'), now()->addDays(self::TTL_DAYS), $callback);
    }

    /**
     * Makes every copy stored under this version stale, once the current transaction (if any) commits.
     */
    public static function newVersion(Repository $cache, string $versionKey): void
    {
        DB::afterCommit(fn () => $cache->forever($versionKey, Str::random(12)));
    }
}
