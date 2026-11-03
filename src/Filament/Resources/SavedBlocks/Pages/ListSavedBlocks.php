<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Blocks\Core\SavedBlockBlock;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\VisualEditor;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Pages\PageImport;

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
            Action::make('import')
                ->label(__('Import'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->authorize('create')
                ->modalHeading(__('Import a library block'))
                ->modalDescription(__('A block or Section from Export, on this site or another. Images that aren\'t on this site are left out.'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('Import'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->helperText(__('How it shows in the library, e.g. "Contact form" or "Partner logos".'))
                        ->required()
                        ->maxLength(100),
                    VisualEditor::exportFileField(),
                ])
                ->action(function (array $data, Action $action): void {
                    $export = VisualEditor::readUploadedExport($data['file'] ?? null);
                    $blocks = is_array($export) ? ($export['blocks'] ?? null) : null;

                    // A library block is one block (often a Section), and never a link to another.
                    $result = match (true) {
                        is_string($export) => $export,
                        ! is_array($blocks) || count($blocks) !== 1 => __('A library block is one block or Section, and this file has :count. Import it into a page instead, then save the part you want to the library.', ['count' => is_array($blocks) ? count($blocks) : 0]),
                        ($blocks[0]['type'] ?? null) === SavedBlockBlock::NAME => __('This is a link to a library block, not a block.'),
                        default => app(PageImport::class)->blocks($blocks),
                    };

                    if (is_string($result) || $result->failed()) {
                        VisualEditor::importRefused($result);
                        $action->halt();
                    }

                    $block = $result->blocks[0];
                    $item = app(BlockLibrary::class)->save($data['name'], $block['type'], $block['data']);

                    VisualEditor::imported($result, __('Saved to the block library.'));
                    $this->redirect(SavedBlockResource::getUrl('visual', ['record' => $item]));
                }),
        ];
    }
}
