<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * What a page import found (PageImport): each problem and warning as "path: message", with the path into the JSON
 * (e.g. "blocks.2.data.items.0.title"), and the page once it's saved.
 */
final readonly class PageImportResult
{
    /**
     * @param  array<int, string>  $errors  why the page wasn't saved
     * @param  array<int, string>  $warnings  saved anyway, but worth a look
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
        public ?Page $page = null,
        public bool $isNew = false,
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }
}
