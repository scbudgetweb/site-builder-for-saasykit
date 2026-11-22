<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

/**
 * Edit as a form (pages, library blocks, templates) under an edit lock (HoldsEditLock): without the lock the form
 * is read-only, Save is locked with who's editing in its tooltip, and a save is refused on the server.
 */
trait LocksRecordForm
{
    use HoldsEditLock;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(fn (): bool => $this->editLockHolder !== null);
    }

    protected function beforeSave(): void
    {
        if ($this->refusesWhileLocked(verify: true)) {
            throw new Halt;
        }
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled(fn (): bool => $this->editLockHolder !== null)
            ->icon(fn (): ?Heroicon => $this->editLockHolder !== null ? Heroicon::OutlinedInformationCircle : null)
            ->tooltip(fn (): ?string => $this->editLockMessage());
    }
}
