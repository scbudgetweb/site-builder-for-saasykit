<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages;

use Filament\Resources\Pages\CreateRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns\ReportsBlockValidationErrors;

class CreatePage extends CreateRecord
{
    use ReportsBlockValidationErrors;

    protected static string $resource = PageResource::class;

    /**
     * Straight into the editor, where the block panels have Save page and Preview.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
