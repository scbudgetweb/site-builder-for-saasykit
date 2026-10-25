<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;

class ListSavedBlocks extends ListRecords
{
    protected static string $resource = SavedBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New library block')),
        ];
    }
}
