<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Pages\PageService;

/**
 * A block (or Section) in the block library (Admin › Website › Block library), reused on pages through linked
 * "Saved block" blocks, so editing it here updates every page that uses it (see BlockLibrary).
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property array<int|string, mixed>|null $blocks
 * @property Carbon|null $updated_at
 */
class SavedBlock extends Model
{
    protected $table = 'site_builder_saved_blocks';

    protected $fillable = ['name', 'type', 'blocks'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    /**
     * Plain slashes, so upload paths match a plain LIKE, as on pages (lesson 13).
     *
     * @param  mixed  $value
     * @param  int  $flags
     */
    protected function asJson($value, $flags = 0): string|false
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected static function booted(): void
    {
        // The type follows the saved block, for the library list.
        static::saving(function (SavedBlock $savedBlock): void {
            $savedBlock->type = $savedBlock->block()['type'] ?? 'unknown';
        });

        // Images removed from an item are deleted unless a page or another item still uses them.
        static::updated(function (SavedBlock $savedBlock): void {
            if (! $savedBlock->wasChanged('blocks')) {
                return;
            }

            $pages = app(PageService::class);
            $before = $pages->uploadedPaths(json_decode((string) $savedBlock->getRawOriginal('blocks'), true) ?? []);

            $pages->deleteUnusedUploads(array_values(array_diff($before, $pages->uploadedPaths($savedBlock->blocks ?? []))), exceptSavedBlock: $savedBlock);
        });

        // Pages linking to it show its block, so cached page HTML is stale.
        static::saved(fn () => RenderCache::flush());

        static::deleted(function (SavedBlock $savedBlock): void {
            RenderCache::flush();

            $pages = app(PageService::class);

            $pages->deleteUnusedUploads($pages->uploadedPaths($savedBlock->blocks ?? []), exceptSavedBlock: $savedBlock);
        });
    }

    /**
     * The saved block, as {type, data}, or null if it's missing or damaged.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    public function block(): ?array
    {
        $block = collect($this->blocks ?? [])->first();

        return is_array($block) && is_string($block['type'] ?? null) && is_array($block['data'] ?? null)
            ? ['type' => $block['type'], 'data' => $block['data']]
            : null;
    }
}
