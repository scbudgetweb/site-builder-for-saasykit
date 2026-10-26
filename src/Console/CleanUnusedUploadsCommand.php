<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Storage;
use ScBudgetWeb\SiteBuilder\Branding\Branding;
use ScBudgetWeb\SiteBuilder\Branding\BrandingField;
use ScBudgetWeb\SiteBuilder\Pages\PageService;

/**
 * Deletes uploads that nothing uses:
 * - page images that no page (including the bin), library block or kept revision uses;
 * - Branding images (logos, icons, banner images, app sections' images) that no Branding setting uses.
 * They're left behind when an image is uploaded and the page or Branding is then never saved. Only files older than a
 * day are touched, so an editor that's still open keeps its new uploads. Schedule it in the host app, e.g. daily.
 */
class CleanUnusedUploadsCommand extends Command implements Isolatable
{
    protected $signature = 'site-builder:clean-uploads
        {--hours=24 : Only delete files older than this many hours}
        {--dry-run : List the files without deleting them}';

    protected $description = 'Delete site builder page and Branding images that nothing uses';

    public function handle(PageService $pages, Branding $branding): int
    {
        $disk = Storage::disk($pages->uploadDisk());
        $olderThan = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $isDryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        $brandingInUse = array_flip($branding->usedUploads());
        $unused = [
            ...array_filter($disk->allFiles($pages->uploadDirectory()), fn (string $path): bool => $pages->isPageUpload($path) && ! $pages->isUploadInUse($path)),
            ...array_filter($disk->allFiles($branding->uploadDirectory()), fn (string $path): bool => BrandingField::isBrandingUpload($path) && ! isset($brandingInUse[$path])),
        ];

        foreach ($unused as $path) {
            if ($disk->lastModified($path) > $olderThan) {
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
