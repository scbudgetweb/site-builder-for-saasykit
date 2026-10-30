<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;
use JsonException;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

/**
 * Builds a page from JSON (PageImport): checked by the page form and each block's fields, as Save in the editor
 * does, so agents, scripts and template sites can make pages reliably. Nothing is saved when there's a problem.
 */
class ImportPageCommand extends Command
{
    protected $signature = 'site-builder:page
        {file : The page as a JSON file, or - to read it from standard input}
        {--update : Change the page with this slug if there is one (only the settings given change)}
        {--dry-run : Check the page without saving it}';

    protected $description = 'Create or update a site builder page from JSON, checked as the editor checks it';

    public function handle(PageImport $import): int
    {
        $file = (string) $this->argument('file');
        $json = $file === '-' ? (string) stream_get_contents(STDIN) : (is_file($file) ? (string) file_get_contents($file) : null);

        if ($json === null) {
            $this->error(__('No file at :file.', ['file' => $file]));

            return self::FAILURE;
        }

        try {
            $input = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error(__('Not valid JSON: :reason', ['reason' => $exception->getMessage()]));

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $result = $import->import($input, $file === '-' ? getcwd() ?: null : dirname((string) realpath($file)), (bool) $this->option('update'), $isDryRun);

        foreach ($result->errors as $error) {
            $this->line('<fg=red>✗</> '.$error);
        }

        foreach ($result->warnings as $warning) {
            $this->line('<fg=yellow>!</> '.$warning);
        }

        if ($result->failed()) {
            $this->error(trans_choice(':count problem: nothing was saved.|:count problems: nothing was saved.', count($result->errors), ['count' => count($result->errors)]));

            return self::FAILURE;
        }

        if ($isDryRun || $result->page === null) {
            $this->info(__('The page is fine (nothing saved: dry run).'));

            return self::SUCCESS;
        }

        $this->info(($result->isNew ? __('Created') : __('Updated')).': '.$result->page->title.' ('.$result->page->status->label().', '.$result->page->url().')');

        return self::SUCCESS;
    }
}
