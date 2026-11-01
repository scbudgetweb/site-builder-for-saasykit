<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\StandardHeaderAndFooter;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;

/**
 * The Theme builder: the standard header and footer (where they're shown, and where their links and colours are set),
 * then the templates.
 */
class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    public function getSubheading(): ?string
    {
        return __('Headers and footers built from blocks. Where one applies, it\'s shown in place of the standard header or footer.');
    }

    public function content(Schema $schema): Schema
    {
        // Filament's own list content (ListRecords::content()), after the cards.
        return $schema->components([
            StandardHeaderAndFooter::cards(),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('New template'))];
    }
}
