<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\Pages;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use ScBudgetWeb\SiteBuilder\Blocks\BlockLibrary;
use ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns\ReportsBlockValidationErrors;
use ScBudgetWeb\SiteBuilder\Filament\Resources\SavedBlocks\SavedBlockResource;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * @method SavedBlock getRecord()
 */
class EditSavedBlock extends EditRecord
{
    use ReportsBlockValidationErrors;

    protected static string $resource = SavedBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::refuseDeleteWhileInUse(DeleteAction::make()),
        ];
    }

    /**
     * A library block that pages use can't be deleted: they would silently lose it. Says which pages to change.
     */
    public static function refuseDeleteWhileInUse(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, SavedBlock $record): void {
            $pages = app(BlockLibrary::class)->usedOn($record);

            if ($pages->isEmpty()) {
                return;
            }

            Notification::make()
                ->title(__('This block is still in use'))
                ->body(trans_choice(
                    'It\'s used on :pages. Remove it from that page, or use "Make an editable copy" there, first.|It\'s used on :pages. Remove it from those pages, or use "Make an editable copy" there, first.',
                    $pages->count(),
                    ['pages' => $pages->pluck('title')->implode(', ')],
                ))
                ->danger()
                ->persistent()
                ->send();

            $action->cancel();
        });
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('Saved. Every page using this block now shows the change.');
    }
}
