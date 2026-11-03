<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\StandardHeaderAndFooter;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Templates\TemplateResource;
use ScBudgetWeb\SiteBuilder\Models\Template;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;
use ScBudgetWeb\SiteBuilder\Templates\HeaderBehaviour;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

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
        return [
            CreateAction::make()->label(__('New template')),
            Action::make('import')
                ->label(__('Import'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->authorize('create')
                ->modalHeading(__('Import a template'))
                ->modalDescription(__('A template from Export, on this site or another. It comes in turned off, to check before you turn it on. Images that aren\'t on this site are left out.'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('Import'))
                ->schema([
                    TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                    VisualEditor::exportFileField(),
                ])
                ->action(function (array $data, Action $action): void {
                    $export = VisualEditor::readUploadedExport($data['file'] ?? null);
                    $type = is_array($export) ? ($export['template']['type'] ?? null) : null;
                    $types = app(Templates::class)->types();

                    $problem = match (true) {
                        is_string($export) => $export,
                        $export['kind'] !== 'template' => __('This file isn\'t a template. Make a template, then import a page or Section into it in the visual builder.'),
                        ! is_string($type) || ! isset($types[$type]) => __('This site has no templates of this type (:type).', ['type' => (string) $type]),
                        default => null,
                    };

                    // Checked with the blocks a template of its type offers (a header's menu, a footer's links…).
                    app(BlockRegistry::class)->editingTemplate(is_string($type) ? $type : null);
                    $result = $problem ?? app(PageImport::class)->blocks($export['blocks']);

                    if (is_string($result) || $result->failed()) {
                        VisualEditor::importRefused($result);
                        $action->halt();
                    }

                    $template = Template::query()->create([
                        'name' => $data['name'],
                        'type' => $type,
                        'is_active' => false,
                        'settings' => $type === 'header' ? HeaderBehaviour::clean($export['template']['settings'] ?? null) : null,
                        'blocks' => $result->blocks,
                    ]);

                    VisualEditor::imported($result, __('It\'s off: turn it on in the list when it\'s ready.'));
                    $this->redirect(TemplateResource::getUrl('visual', ['record' => $template]));
                }),
        ];
    }
}
