<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use ScBudgetWeb\SiteBuilder\Blocks\BlockRegistry;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\PageResource;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns\ReportsBlockValidationErrors;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Pages\PageRevisions;

/**
 * @method Page getRecord()
 */
class EditPage extends EditRecord
{
    use ReportsBlockValidationErrors;

    protected static string $resource = PageResource::class;

    /** The Builder's block panels (edit, add at the end, add between), which Save page can submit. */
    private const BLOCK_PANEL_ACTIONS = ['edit', 'add', 'addBetween'];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('Preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Page $record): string => $record->previewUrl(), shouldOpenInNewTab: true),
            Action::make('view')
                ->label(__('View page'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (Page $record): bool => $record->isPublished() && ! $record->trashed())
                ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true),
            Action::make('revisions')
                ->label(__('Revisions'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->slideOver()
                ->modalHeading(__('Revisions'))
                ->modalDescription(__('Each save that changes the page is kept, newest first (the last :count). Restore puts a version back in the editor; save the page to keep it.', ['count' => app(PageRevisions::class)->keep()]))
                ->modalContent(fn (Page $record): View => view('site-builder::filament.pages.revisions', [
                    'revisions' => $record->revisions()->latest('id')->get(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close')),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->label(__('Delete permanently'))
                ->modalDescription(__('This page and its images are removed for good and can\'t be restored. Its URL becomes free to use again.')),
        ];
    }

    /**
     * Reloads a preview opened from a block panel after each save. Not visible.
     */
    public function getFooter(): ?View
    {
        return view('site-builder::filament.pages.preview-reloader');
    }

    /**
     * Saves the page from a block panel's Save page button, with the panel open. An edit panel stays open: its changes
     * are copied into the page, as its own Save changes button would, then the page is saved. A new block only exists
     * once its panel is submitted, so a panel adding one is finished (closed) first. Any other dialog must be closed first.
     *
     * Relies on Filament's mounted-action internals (lesson 10): keep EditPageTest's Save page tests when upgrading Filament.
     */
    public function saveFromEditor(): void
    {
        $names = array_column($this->mountedActions, 'name');

        if (array_diff($names, self::BLOCK_PANEL_ACTIONS) !== []) {
            Notification::make()->title(__('Close the open dialog first.'))->warning()->send();

            return;
        }

        if (array_intersect($names, ['add', 'addBetween']) !== []) {
            while ($this->mountedActions !== []) {
                $openPanels = count($this->mountedActions);

                // A validation error throws and stays in its panel; a panel that didn't close stops the save.
                $this->callMountedAction();

                if (count($this->mountedActions) >= $openPanels) {
                    return;
                }
            }
        } else {
            for ($index = count($this->mountedActions) - 1; $index >= 0; $index--) {
                $action = $this->getMountedAction($index);
                $schema = $this->getMountedActionSchema($index, $action);

                $action->data($schema->getState());
                $action->call(['form' => $schema, 'schema' => $schema]);
                $action->resetData();
            }
        }

        $this->save();
    }

    /**
     * Pages › Revisions › Restore: loads the revision's title, content, banner and SEO settings into the editor as
     * unsaved changes. The address, status and replaced page stay as they are.
     */
    public function restoreRevision(int $revisionId): void
    {
        $revision = $this->getRecord()->revisions()->find($revisionId);

        if ($revision === null) {
            Notification::make()->title(__('That revision no longer exists.'))->danger()->send();

            return;
        }

        $this->form->fill([...$this->form->getRawState(), ...$revision->attributesToRestore()]);

        $this->unmountAction();

        Notification::make()
            ->title(__('Revision loaded'))
            ->body(__('The version saved :date is in the editor. Save the page to keep it, or leave without saving to discard it.', [
                'date' => $revision->created_at?->translatedFormat('j M Y, H:i'),
            ]))
            ->success()
            ->persistent()
            ->send();
    }

    protected function afterSave(): void
    {
        $this->dispatch('site-builder-page-saved');

        $slot = $this->getRecord()->slot();
        $requiredBlock = $slot?->requiredBlock();

        if ($requiredBlock !== null && ! $this->getRecord()->hasBlock($requiredBlock)) {
            Notification::make()
                ->title(__('This page is missing a block'))
                ->body(__('It replaces :page, which needs the ":block" block to keep working. Add the block, or stop replacing :page.', [
                    'page' => $slot->label(),
                    'block' => app(BlockRegistry::class)->labelFor($requiredBlock),
                ]))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
