<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;

class ListSavedBlocks extends ListRecords
{
    protected static string $resource = SavedBlockResource::class;

    public function getSubheading(): ?string
    {
        return __('Blocks you can reuse on many pages, such as an opening-hours panel or a call to action. Change one here and every page it\'s linked on updates.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New library block')),
        ];
    }
}
