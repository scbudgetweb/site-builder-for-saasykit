<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SectionBlock;
use ScBudgetWeb\SiteBuilder\Blocks\RenderCache;
use ScBudgetWeb\SiteBuilder\Blocks\StoredBlocks;
use ScBudgetWeb\SiteBuilder\Pages\PageRevisions;
use ScBudgetWeb\SiteBuilder\Pages\PageService;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Pages\Slot;
use ScBudgetWeb\SiteBuilder\SiteBuilder;

/**
 * A public page built from blocks (Admin › Website › Pages). Served at /{slug}, or in place of a built-in page
 * when it replaces one (see Slot).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 * @property string|null $replaces
 * @property bool $show_page_banner
 * @property array<int|string, mixed>|null $blocks
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $share_image
 * @property bool $noindex
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class Page extends Model
{
    use SoftDeletes;

    protected $table = 'site_builder_pages';

    protected $fillable = [
        'title',
        'slug',
        'status',
        'replaces',
        'show_page_banner',
        'blocks',
        'seo_title',
        'seo_description',
        'share_image',
        'noindex',
        'published_at',
    ];

    /**
     * Uploads used only by the revisions of a page being deleted for good, cleaned up once it has gone.
     *
     * @var array<int, string>
     */
    private array $revisionUploadsToClean = [];

    protected $attributes = [
        'status' => 'draft',
        'show_page_banner' => true,
        'noindex' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'show_page_banner' => 'boolean',
            'blocks' => StoredBlocks::class,
            'noindex' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Stored with plain slashes, so upload paths in the blocks match a plain LIKE (PageService::deleteUnusedUploads()):
     * an escaped "\/" would need a LIKE escape character, which MySQL and SQLite treat differently (lesson 13).
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
        static::saving(function (Page $page): void {
            if ($page->status === PageStatus::Published && $page->published_at === null) {
                $page->published_at = now();
            }
        });

        // Free the slot so another page can take it while this one sits in the bin.
        static::deleting(function (Page $page): void {
            if (! $page->isForceDeleting() && $page->replaces !== null) {
                $page->replaces = null;
                $page->saveQuietly();
            }
        });

        // Images removed from the blocks (or a replaced share image) are deleted from storage.
        static::updated(function (Page $page): void {
            if (! $page->wasChanged(['blocks', 'share_image'])) {
                return;
            }

            $pages = app(PageService::class);
            $before = $pages->uploadedPaths([json_decode((string) $page->getRawOriginal('blocks'), true) ?? [], $page->getRawOriginal('share_image')]);
            $after = $pages->uploadedPaths([$page->blocks ?? [], $page->share_image]);

            $pages->deleteUnusedUploads(array_values(array_diff($before, $after)), $page);
        });

        // Each save that changes the content, banner or SEO settings is kept as a revision.
        static::created(fn (Page $page) => app(PageRevisions::class)->record($page, isNew: true));
        static::updated(fn (Page $page) => app(PageRevisions::class)->record($page));

        // Deleted for good from the bin: remove its revisions and images too, unless another page still uses them.
        static::forceDeleting(function (Page $page): void {
            $page->revisionUploadsToClean = app(PageRevisions::class)->deleteAll($page);
        });

        static::forceDeleted(function (Page $page): void {
            $pages = app(PageService::class);

            $pages->deleteUnusedUploads(array_values(array_unique([...$pages->uploadedPaths([$page->blocks ?? [], $page->share_image]), ...$page->revisionUploadsToClean])), $page);
        });

        $clearLookups = function (): void {
            app(PageService::class)->clearCache();
            RenderCache::flush();
        };

        static::saved($clearLookups);
        static::deleted($clearLookups);
        static::restored($clearLookups);
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PageStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    /**
     * The registered built-in page this page replaces (null when none, or when that slot is no longer registered).
     */
    public function slot(): ?Slot
    {
        return app(SiteBuilder::class)->slot($this->replaces);
    }

    /**
     * The public URL: the built-in page's URL when this page replaces one, otherwise /{slug}.
     */
    public function url(): string
    {
        return $this->slot()?->url() ?? url($this->slug);
    }

    /**
     * A temporary signed link that shows the page whatever its status (the admin Preview buttons).
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('site-builder.pages.preview', now()->addHour(), ['page' => $this->getKey()]);
    }

    public function metaTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    /**
     * The saved blocks that have the expected shape, in order, with linked library blocks swapped for the library's
     * block (BlockLibrary::expand()).
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public function blockList(): array
    {
        return self::validBlocks(app(BlockLibrary::class)->expand($this->blocks ?? []));
    }

    /**
     * Whether the page has a block of this type.
     */
    public function hasBlock(string $type): bool
    {
        return self::listContains($this->blockList(), $type);
    }

    /**
     * Whether saved blocks (or the editor's Builder state, keyed by item ID) include a block of this type, at the top
     * level or in a Section's rows. A linked library block counts as the block it links to.
     *
     * @param  array<int|string, mixed>  $blocks
     */
    public static function blocksContain(array $blocks, string $type): bool
    {
        return self::listContains(app(BlockLibrary::class)->expand($blocks), $type);
    }

    /**
     * Whether a block on the page, at the top level or in a Section's rows, has this anchor (its Advanced › Anchor
     * ID, or the block's default anchor), so a link to #{anchor} has somewhere to go.
     */
    public function hasAnchor(string $anchor): bool
    {
        return self::listHasAnchor($this->blockList(), $anchor);
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     */
    private static function listHasAnchor(array $blocks, string $anchor): bool
    {
        $registry = app(BlockRegistry::class);

        foreach (self::validBlocks($blocks) as $block) {
            $blockClass = $registry->find($block['type']);

            if ($blockClass === null || ! $blockClass::hasAppearance()) {
                continue;
            }

            $saved = $block['data']['appearance']['anchor'] ?? null;
            $blockAnchor = is_string($saved) && preg_match('/^[A-Za-z0-9_-]+$/', $saved) ? $saved : $blockClass::defaultAnchor();

            if ($blockAnchor === $anchor) {
                return true;
            }

            if ($block['type'] === SectionBlock::NAME) {
                foreach (SectionBlock::rows($block['data']) as $row) {
                    if (self::listHasAnchor($row['blocks'], $anchor)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     */
    private static function listContains(array $blocks, string $type): bool
    {
        foreach (self::validBlocks($blocks) as $block) {
            if ($block['type'] === $type) {
                return true;
            }

            if ($block['type'] === SectionBlock::NAME) {
                foreach (SectionBlock::rows($block['data']) as $row) {
                    if (self::listContains($row['blocks'], $type)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private static function validBlocks(array $blocks): array
    {
        return array_values(array_filter(
            $blocks,
            fn (mixed $block): bool => is_array($block) && is_string($block['type'] ?? null) && is_array($block['data'] ?? null),
        ));
    }
}
