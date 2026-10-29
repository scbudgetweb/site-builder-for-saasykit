<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    public function getSubheading(): ?string
    {
        return __('Headers and footers built from blocks. Where one applies, it\'s shown in place of the standard header or footer.');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('New template'))];
    }
}
