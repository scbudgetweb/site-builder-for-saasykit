<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;

/**
 * One editor at a time for a page, library block or template. Opening its visual builder or form takes the lock,
 * which the open editor renews every HEARTBEAT seconds for as long as it's open, however idle; closing it releases
 * the lock, and a closed tab or lost connection lets it lapse after EXPIRES seconds. Anyone else gets it read-only.
 * Kept in the cache (the file cache works), so a cache clear only frees every lock early.
 */
class EditLocks
{
    /** How often an open editor renews its lock, in seconds. */
    public const HEARTBEAT = 30;

    /** How long a lock lasts without being renewed: long enough for a background tab's slowed timers. */
    public const EXPIRES = 120;

    /** @var array<string, array{id: string, name: string, since: string}|null> this request's lookups */
    private array $holders = [];

    /**
     * Takes or renews the lock for $user. False when someone else holds it.
     */
    public function claim(Model $record, Authenticatable $user): bool
    {
        $key = $this->key($record);

        $claimed = Cache::lock($key.':claim', 5)->block(3, function () use ($key, $user): bool {
            $holder = $this->current($key);

            if ($holder !== null && $holder['id'] !== (string) $user->getAuthIdentifier()) {
                return false;
            }

            Cache::put($key, [
                'id' => (string) $user->getAuthIdentifier(),
                'name' => self::nameOf($user),
                'since' => $holder['since'] ?? now()->toIso8601String(),
            ], self::EXPIRES);

            return true;
        });

        unset($this->holders[$key]);

        return $claimed;
    }

    /**
     * Frees the lock if $userId holds it (closing the editor).
     */
    public function release(Model $record, int|string $userId): void
    {
        $key = $this->key($record);

        Cache::lock($key.':claim', 5)->block(3, function () use ($key, $userId): void {
            if (($this->current($key)['id'] ?? null) === (string) $userId) {
                Cache::forget($key);
            }
        });

        unset($this->holders[$key]);
    }

    /**
     * Who's editing it when that's someone other than $user: their name and since when. Null when it's free or theirs.
     *
     * @return array{id: string, name: string, since: string}|null
     */
    public function heldByOther(Model $record, ?Authenticatable $user): ?array
    {
        $holder = $this->holder($record);

        return $holder !== null && $holder['id'] !== (string) $user?->getAuthIdentifier() ? $holder : null;
    }

    /**
     * The person holding the lock (looked up once per request, for the lists' rows).
     *
     * @return array{id: string, name: string, since: string}|null
     */
    public function holder(Model $record): ?array
    {
        $key = $this->key($record);

        return array_key_exists($key, $this->holders) ? $this->holders[$key] : $this->holders[$key] = $this->current($key);
    }

    /**
     * "<name> is editing this page" (block, template).
     */
    public static function message(Model $record, string $name): string
    {
        return match (true) {
            $record instanceof SavedBlock => __(':name is editing this block', ['name' => $name]),
            $record instanceof Template => __(':name is editing this template', ['name' => $name]),
            default => __(':name is editing this page', ['name' => $name]),
        };
    }

    /**
     * The kind and id in a lock's key: page, block or template.
     */
    public static function kindOf(Model $record): string
    {
        return match (true) {
            $record instanceof Page => 'page',
            $record instanceof SavedBlock => 'block',
            $record instanceof Template => 'template',
            default => throw new \InvalidArgumentException('Only pages, library blocks and templates are locked.'),
        };
    }

    /**
     * @return class-string<Model>|null
     */
    public static function modelFor(string $kind): ?string
    {
        return ['page' => Page::class, 'block' => SavedBlock::class, 'template' => Template::class][$kind] ?? null;
    }

    private function key(Model $record): string
    {
        return 'site-builder:edit-lock:'.self::kindOf($record).':'.$record->getKey();
    }

    /**
     * @return array{id: string, name: string, since: string}|null
     */
    private function current(string $key): ?array
    {
        $holder = Cache::get($key);

        return is_array($holder) && isset($holder['id'], $holder['name'], $holder['since']) ? $holder : null;
    }

    private static function nameOf(Authenticatable $user): string
    {
        $name = $user->name ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : (is_string($user->email ?? null) ? $user->email : __('Someone'));
    }
}
