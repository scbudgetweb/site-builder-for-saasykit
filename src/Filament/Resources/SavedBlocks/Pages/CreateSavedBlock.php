<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Filament\Resources\Pages\CreateRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns\ReportsBlockValidationErrors;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Schemas\SavedBlockForm;

/**
 * New library block: a name and what to start with (a Section unless another block is chosen), then straight into
 * the visual builder to build it, as New page does.
 */
class CreateSavedBlock extends CreateRecord
{
    use ReportsBlockValidationErrors;

    protected static string $resource = SavedBlockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['start_with'] ?? null;
        abort_unless(is_string($type) && isset(SavedBlockForm::startingBlocks()[$type]), 422);

        return ['name' => $data['name'], 'blocks' => [['type' => $type, 'data' => []]]];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('visual', ['record' => $this->getRecord()]);
    }
}
