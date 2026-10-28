<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getSubheading(): ?string
    {
        return __('Your website\'s own pages, each built from blocks. A page stays a draft, hidden from visitors, until you publish it.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New page')),
        ];
    }
}
