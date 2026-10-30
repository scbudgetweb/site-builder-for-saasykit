<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * The page form without a screen (PageImport): it holds the form's state while an imported page is checked, so the
 * page is validated by the same fields, rules and blocks as Save in the editor. Never rendered or routed.
 */
class PageImportForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];
}
