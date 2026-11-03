<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns\ReportsBlockValidationErrors;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageExport;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;

class EditTemplate extends EditRecord
{
    use ReportsBlockValidationErrors;

    protected static string $resource = TemplateResource::class;

    /**
     * The block pickers offer this template type's own blocks (a header's menu, a footer's links…).
     */
    public function booted(): void
    {
        $template = $this->getRecord();

        app(BlockRegistry::class)->editingTemplate($template instanceof Template ? $template->type : null);
    }

    /**
     * A header's settings open on the standard behaviour where nothing (or something unexpected) is saved.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->isHeader() ? [...$data, 'settings' => HeaderBehaviour::clean($data['settings'] ?? null)] : $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->isHeader() && array_key_exists('settings', $data)) {
            $data['settings'] = HeaderBehaviour::clean($data['settings']);
        }

        return $data;
    }

    private function isHeader(): bool
    {
        $template = $this->getRecord();

        return $template instanceof Template && $template->type === 'header';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('visual')
                ->label(__('Visual builder'))
                ->tooltip(__('Build it from blocks, as it looks'))
                ->icon(Heroicon::OutlinedPaintBrush)
                ->url(fn (Template $record): string => TemplateResource::getUrl('visual', ['record' => $record])),
            BlockRegistry::importPageAction(),
            PageExport::recordAction(),
            DeleteAction::make(),
        ];
    }
}
