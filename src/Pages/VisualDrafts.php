<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageDraft;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Models\Template;

/**
 * The visual builder's drafts: one per person and page (or block library item, or Theme Builder template), saved with
 * every change. A draft is only stored once something
 * changes, so opening the builder leaves nothing behind. The page itself only changes when its draft is published,
 * which first checks that nobody has saved the page since the draft started.
 */
class VisualDrafts
{
    /** How many earlier versions undo keeps. */
    public const HISTORY = 30;

    /**
     * This person's draft of the page, or (not stored until it changes) a new one, started from the page as it's saved now.
     */
    public function open(Page|SavedBlock|Template $record, Authenticatable $user): PageDraft
    {
        return $this->find($record, $user) ?? new PageDraft([
            self::ownerColumn($record) => $record->getKey(),
            'user_id' => (int) $user->getAuthIdentifier(),
            'blocks' => $record->blocks ?? [],
            'base_updated_at' => $record->updated_at,
        ]);
    }

    public function find(Page|SavedBlock|Template $record, Authenticatable $user): ?PageDraft
    {
        return PageDraft::query()->where(self::ownerColumn($record), $record->getKey())->where('user_id', (int) $user->getAuthIdentifier())->first();
    }

    private static function ownerColumn(Page|SavedBlock|Template $record): string
    {
        return match (true) {
            $record instanceof SavedBlock => 'saved_block_id',
            $record instanceof Template => 'template_id',
            default => 'page_id',
        };
    }

    /**
     * Saves new blocks into the draft, keeping the old ones for undo (and forgetting anything undone). The same blocks
     * change nothing.
     *
     * @param  array<int|string, mixed>  $blocks
     */
    public function change(PageDraft $draft, array $blocks): void
    {
        // Saving a panel without changing anything leaves nothing to undo (and, for a new draft, stores nothing).
        if ($blocks == ($draft->blocks ?? [])) {
            return;
        }

        $draft->fill([
            'history' => array_slice([...($draft->history ?? []), $draft->blocks ?? []], -self::HISTORY),
            'future' => [],
            'blocks' => $blocks,
        ])->save();
    }

    public function canUndo(PageDraft $draft): bool
    {
        return ($draft->history ?? []) !== [];
    }

    public function canRedo(PageDraft $draft): bool
    {
        return ($draft->future ?? []) !== [];
    }

    public function undo(PageDraft $draft): void
    {
        $history = $draft->history ?? [];

        if ($history === []) {
            return;
        }

        $draft->fill([
            'future' => [...($draft->future ?? []), $draft->blocks ?? []],
            'blocks' => array_pop($history),
            'history' => $history,
        ])->save();
    }

    public function redo(PageDraft $draft): void
    {
        $future = $draft->future ?? [];

        if ($future === []) {
            return;
        }

        $draft->fill([
            'history' => array_slice([...($draft->history ?? []), $draft->blocks ?? []], -self::HISTORY),
            'blocks' => array_pop($future),
            'future' => $future,
        ])->save();
    }

    /**
     * Whether the draft differs from the page as it's saved now.
     */
    public function hasChanges(PageDraft $draft, Page|SavedBlock|Template $record): bool
    {
        return ($draft->blocks ?? []) != ($record->blocks ?? []);
    }

    /**
     * Whether the page has been saved (by anyone, or in the form editor) since the draft started.
     */
    public function isOutOfDate(PageDraft $draft, Page|SavedBlock|Template $record): bool
    {
        return $record->updated_at !== null && ($draft->base_updated_at === null || $record->updated_at->ne($draft->base_updated_at));
    }

    /**
     * Copies the draft's blocks to the page (a revision is recorded as for any save), then the draft goes.
     */
    public function publish(PageDraft $draft, Page|SavedBlock|Template $record): void
    {
        DB::transaction(function () use ($draft, $record): void {
            $blocks = $draft->blocks ?? [];

            if ($draft->exists) {
                $draft->delete();
            }

            $record->update(['blocks' => $blocks]);
        });
    }

    public function discard(PageDraft $draft): void
    {
        if ($draft->exists) {
            $draft->delete();
        }
    }
}
