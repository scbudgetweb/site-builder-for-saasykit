<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;
use ScBudgetWeb\SiteBuilder\Media\MediaLibrary;

/**
 * Adds files in the pages upload folder that aren't in Website › Media's Library tab yet, such as those uploaded before it
 * existed (the migration does this once) or copied in by hand. Files in the library are kept until someone deletes them.
 */
class SyncMediaLibraryCommand extends Command
{
    protected $signature = 'site-builder:media-sync';

    protected $description = 'Add files in the site builder\'s pages upload folder to the media library';

    public function handle(MediaLibrary $library): int
    {
        $added = $library->recordExisting();

        $this->info(trans_choice(':count file added to the media library.|:count files added to the media library.', $added, ['count' => $added]));

        return self::SUCCESS;
    }
}
