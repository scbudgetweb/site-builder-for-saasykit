<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Models\PageRevision;

/**
 * Page revisions (D0a-5): a snapshot of the page after every save that changes its content, banner or SEO settings,
 * and who saved it. The last few per page are kept (config site-builder.pages.revisions, 30 by default, D0a-11).
 *
 * Images in a kept revision stay in storage (PageService::deleteUnusedUploads() checks revisions), so restoring one
 * never brings back a missing image. Once the last revision using an image is pruned, the image goes too.
 */
class PageRevisions
{
    public function __construct(private PageService $pages) {}

    public function keep(): int
    {
        return max(1, (int) config('site-builder.pages.revisions', 30));
    }

    /**
     * Records the page as just saved: always when it's new, otherwise only if its content, banner or SEO settings
     * changed. Called from the page's created and updated events (wasRecentlyCreated stays true on the model after
     * creating it, so it can't tell a later save apart).
     */
    public function record(Page $page, bool $isNew = false): void
    {
        if (! $isNew && ! $page->wasChanged(PageRevision::ATTRIBUTES)) {
            return;
        }

        $user = auth()->user();

        $page->revisions()->create([
            'snapshot' => $page->only(PageRevision::ATTRIBUTES),
            'user_id' => is_numeric($user?->getAuthIdentifier()) ? (int) $user->getAuthIdentifier() : null,
            'user_name' => is_string($user?->getAttribute('name')) ? $user->getAttribute('name') : null,
        ]);

        $this->prune($page);
    }

    /**
     * Removes all but the newest revisions, then the images only they used.
     */
    public function prune(Page $page): void
    {
        $old = $page->revisions()->latest('id')->skip($this->keep())->take(PHP_INT_MAX)->get();

        if ($old->isEmpty()) {
            return;
        }

        $paths = $this->uploadedPaths($old->all());

        PageRevision::query()->whereKey($old->modelKeys())->delete();

        $this->pages->deleteUnusedUploads($paths);
    }

    /**
     * Deletes a page's revisions (it's being deleted for good) and returns the uploads they used, to clean up once the
     * page itself has gone.
     *
     * @return array<int, string>
     */
    public function deleteAll(Page $page): array
    {
        $revisions = $page->revisions()->get();
        $paths = $this->uploadedPaths($revisions->all());

        PageRevision::query()->whereKey($revisions->modelKeys())->delete();

        return $paths;
    }

    /**
     * @param  array<int, PageRevision>  $revisions
     * @return array<int, string>
     */
    private function uploadedPaths(array $revisions): array
    {
        return $this->pages->uploadedPaths(array_map(fn (PageRevision $revision): array => $revision->attributesToRestore(), $revisions));
    }
}
