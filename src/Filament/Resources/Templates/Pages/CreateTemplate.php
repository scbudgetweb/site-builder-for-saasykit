<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Resources\Pages\CreateRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;
use ScBudgetWeb\SiteBuilder\Templates\TemplateLayouts;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * A new template starts with its name, type and where it applies, and a layout in blocks: for a header or footer, the
 * one picked (TemplateLayouts: the standard one as standard, or empty); for a blog template, its standard layout. Then
 * it opens in the visual builder.
 */
class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $layout = is_string($data['layout'] ?? null) ? $data['layout'] : null;
        unset($data['layout']);

        return [
            ...$data,
            // Only a header has settings (how it behaves as the page scrolls).
            'settings' => $type === 'header' ? HeaderBehaviour::clean($data['settings'] ?? []) : null,
            // A header or footer starts from the layout chosen; a blog template from its standard layout.
            'blocks' => TemplateLayouts::has($type) ? TemplateLayouts::blocks($type, $layout) : Templates::starterBlocks($type),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TemplateResource::getUrl('visual', ['record' => $this->getRecord()]);
    }
}
