<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Resources\Pages\CreateRecord;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * A new template starts with its name, type and where it applies, and its type's standard layout in blocks (a blog
 * post's title, text and so on; a header or footer starts empty), then opens in the visual builder.
 */
class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';

        return [
            ...$data,
            // Only a header has settings (how it behaves as the page scrolls).
            'settings' => $type === 'header' ? HeaderBehaviour::clean($data['settings'] ?? []) : null,
            'blocks' => Templates::starterBlocks($type),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TemplateResource::getUrl('visual', ['record' => $this->getRecord()]);
    }
}
