<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Pages\PageService;

/**
 * Deletes page images that nothing uses: no page (including the bin), library block or kept revision. These are left
 * behind when an image is uploaded in a block's panel and the page is then never saved. Only files older than a day
 * are touched, so an editor that's still open keeps its new uploads. Schedule it in the host app, e.g. daily.
 */
class CleanUnusedUploadsCommand extends Command implements Isolatable
{
    protected $signature = 'site-builder:clean-uploads
        {--hours=24 : Only delete files older than this many hours}
        {--dry-run : List the files without deleting them}';

    protected $description = 'Delete site builder page images that no page, library block or revision uses';

    public function handle(PageService $pages): int
    {
        $disk = Storage::disk($pages->uploadDisk());
        $olderThan = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $isDryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        foreach ($disk->allFiles($pages->uploadDirectory()) as $path) {
            if (! $pages->isPageUpload($path) || $disk->lastModified($path) > $olderThan || $pages->isUploadInUse($path)) {
                continue;
            }

            if (! $isDryRun) {
                $disk->delete($path);
            }

            $this->line(($isDryRun ? 'Unused: ' : 'Deleted: ').$path);
            $deleted++;
        }

        $this->info($isDryRun
            ? trans_choice(':count unused file found (nothing deleted).|:count unused files found (nothing deleted).', $deleted, ['count' => $deleted])
            : trans_choice(':count unused file deleted.|:count unused files deleted.', $deleted, ['count' => $deleted]));

        return self::SUCCESS;
    }
}
