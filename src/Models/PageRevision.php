<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * A saved version of a page (Pages › Revisions): written on every save that changes the page's content, banner or
 * SEO settings. The last few per page are kept (config site-builder.pages.revisions). Restoring loads the snapshot
 * into the editor as unsaved changes.
 *
 * @property int $id
 * @property int $page_id
 * @property array<string, mixed> $snapshot
 * @property int|null $user_id
 * @property string|null $user_name
 * @property Carbon|null $created_at
 */
class PageRevision extends Model
{
    /** The page attributes a revision keeps and restores. The address, status and replaced page stay as they are. */
    public const ATTRIBUTES = ['title', 'show_page_banner', 'blocks', 'seo_title', 'seo_description', 'share_image', 'noindex'];

    public const UPDATED_AT = null;

    protected $table = 'site_builder_page_revisions';

    protected $fillable = ['page_id', 'snapshot', 'user_id', 'user_name'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'created_at' => 'datetime',
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

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The snapshot's known attributes only, so a hand-edited row can't set anything else.
     *
     * @return array<string, mixed>
     */
    public function attributesToRestore(): array
    {
        return array_intersect_key(is_array($this->snapshot) ? $this->snapshot : [], array_flip(self::ATTRIBUTES));
    }

    /**
     * A temporary signed link showing the page as it was in this revision.
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('site-builder.pages.revision-preview', now()->addHour(), ['page' => $this->page_id, 'revision' => $this->getKey()]);
    }
}
