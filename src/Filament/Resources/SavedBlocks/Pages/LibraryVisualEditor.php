<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Illuminate\Contracts\Support\Htmlable;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;
use ScBudgetWeb\SiteBuilder\Pages\VisualDrafts;

/**
 * Block library › Visual builder: the same editor as a page's, for one library item, shown on its own in the theme.
 * An item is one block (often a Section), so only what's inside it can be added or moved. Saving changes every page
 * that uses it, so Save says how many first.
 */
class LibraryVisualEditor extends VisualEditor
{
    protected static string $resource = SavedBlockResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->item()->name;
    }

    public function allowsTopLevelChanges(): bool
    {
        return false;
    }

    public function statusText(): string
    {
        $drafts = app(VisualDrafts::class);
        $draft = $this->draft();

        if ($drafts->isOutOfDate($draft, $this->item())) {
            return __('This library block has been saved somewhere else since you started. Saving will replace those changes.');
        }

        return $drafts->hasChanges($draft, $this->item())
            ? __('Unsaved changes, kept as you go. Pages that use this block change once you save.')
            : __('Click a block to change it. Pages that use this block change once you save.');
    }

    protected function canvasPage(array $blocks): Page
    {
        return $this->item()->previewPage($blocks);
    }

    protected function publishTexts(): array
    {
        $count = app(BlockLibrary::class)->usedOn($this->item())->count();
        $isOutOfDate = app(VisualDrafts::class)->isOutOfDate($this->draft(), $this->item());

        $warning = implode(' ', array_filter([
            $isOutOfDate ? __('Someone saved this library block (:time) since you started, and saving replaces their changes.', ['time' => $this->item()->updated_at?->diffForHumans() ?? '']) : null,
            $count > 0 ? trans_choice('It\'s used on :count page, which changes too.|It\'s used on :count pages, which all change too.', $count, ['count' => $count]) : null,
        ]));

        return [
            'label' => __('Save'),
            'warning' => $warning === '' ? null : $warning,
            'title' => __('Saved to the block library'),
            'body' => $count > 0
                ? trans_choice('The :count page that uses it shows the change.|The :count pages that use it show the change.', $count, ['count' => $count])
                : __('No page uses it yet.'),
        ];
    }

    private function item(): SavedBlock
    {
        $item = $this->getRecord();
        abort_unless($item instanceof SavedBlock, 404);

        return $item;
    }
}
