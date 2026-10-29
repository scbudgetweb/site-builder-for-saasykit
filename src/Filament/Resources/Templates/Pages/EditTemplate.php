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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('visual')
                ->label(__('Visual builder'))
                ->tooltip(__('Build it from blocks, as it looks'))
                ->icon(Heroicon::OutlinedPaintBrush)
                ->url(fn (Template $record): string => TemplateResource::getUrl('visual', ['record' => $record])),
            DeleteAction::make(),
        ];
    }
}
