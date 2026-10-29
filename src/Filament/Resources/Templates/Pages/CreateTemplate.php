<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Resources\Pages\CreateRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;

/**
 * A new template starts empty, with its name, type and where it applies, then opens in the visual builder.
 */
class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return TemplateResource::getUrl('visual', ['record' => $this->getRecord()]);
    }
}
