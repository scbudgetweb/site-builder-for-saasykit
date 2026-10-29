<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use ScBudgetWeb\SiteBuilder\Blocks\StoredBlocks;

/**
 * Someone's work in progress on a page, or a block library item, in the visual builder (VisualDrafts): saved with every
 * change, and copied to the page (or item) when they publish it. Undo and redo keep earlier and later versions of the
 * blocks.
 *
 * @property int $id
 * @property int|null $page_id
 * @property int|null $saved_block_id
 * @property int|null $template_id
 * @property int $user_id
 * @property array<int|string, mixed>|null $blocks
 * @property Carbon|null $base_updated_at
 * @property array<int, array<int|string, mixed>>|null $history
 * @property array<int, array<int|string, mixed>>|null $future
 * @property Carbon|null $updated_at
 */
class PageDraft extends Model
{
    protected $table = 'site_builder_page_drafts';

    protected $fillable = ['page_id', 'saved_block_id', 'template_id', 'user_id', 'blocks', 'base_updated_at', 'history', 'future'];

    protected function casts(): array
    {
        return [
            'blocks' => StoredBlocks::class,
            'base_updated_at' => 'datetime',
            'history' => 'array',
            'future' => 'array',
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
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /**
     * @return BelongsTo<SavedBlock, $this>
     */
    public function savedBlock(): BelongsTo
    {
        return $this->belongsTo(SavedBlock::class);
    }

    /**
     * The canvas: the page (or library item) with this person's draft, or as it's saved until they change something,
     * marked up for the visual builder, through a temporary signed link.
     */
    public function canvasUrl(): string
    {
        return match (true) {
            $this->saved_block_id !== null => URL::temporarySignedRoute('site-builder.library.canvas', now()->addHours(12), ['savedBlock' => $this->saved_block_id, 'user' => $this->user_id]),
            $this->template_id !== null => URL::temporarySignedRoute('site-builder.templates.canvas', now()->addHours(12), ['template' => $this->template_id, 'user' => $this->user_id]),
            default => URL::temporarySignedRoute('site-builder.pages.canvas', now()->addHours(12), ['page' => $this->page_id, 'user' => $this->user_id]),
        };
    }
}
