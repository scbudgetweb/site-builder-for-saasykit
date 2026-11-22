<?php

namespace ScBudgetWeb\SiteBuilder\Filament\Resources\Pages\Pages\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Renderless;
use ScBudgetWeb\SiteBuilder\Pages\EditLocks;

/**
 * One editor at a time (EditLocks): opening the editor takes the lock, the page renews it every EditLocks::HEARTBEAT
 * seconds while it's open (partials/edit-lock) and frees it on leaving. Anyone else gets it read-only: a banner names
 * who's editing, and every action but looking is refused here, on the server, not only hidden.
 */
trait HoldsEditLock
{
    /** Actions that only look, which stay open to someone without the lock. */
    private const LOOKING_ACTIONS = ['preview', 'view', 'viewPage', 'revisions', 'export'];

    /**
     * Who's editing it when that's someone else (name and since when). Null while this person holds the lock.
     *
     * @var array{id: string, name: string, since: string}|null
     */
    public ?array $editLockHolder = null;

    public function mountHoldsEditLock(): void
    {
        $this->claimEditLock();
    }

    /**
     * The heartbeat: renews this person's lock ("held"), or says it was lost to someone else ("lost"). Without the
     * lock: "free" once theirs has lapsed (the banner then offers Start editing, which reloads with fresh content),
     * otherwise "locked". Nothing is re-rendered unless the lock was lost.
     */
    #[Renderless]
    public function keepEditLock(): string
    {
        if ($this->editLockHolder === null) {
            return $this->claimEditLock() ? 'held' : 'lost';
        }

        return app(EditLocks::class)->heldByOther($this->editLockRecord(), auth()->user()) === null ? 'free' : 'locked';
    }

    /**
     * The signed link the page calls as it closes, to free the lock straight away.
     */
    public function editLockReleaseUrl(): ?string
    {
        $user = auth()->user();
        $record = $this->editLockRecord();

        return $user === null ? null : URL::temporarySignedRoute('site-builder.edit-lock.release', now()->addDay(), [
            'kind' => EditLocks::kindOf($record),
            'id' => $record->getKey(),
            'user' => $user->getAuthIdentifier(),
        ]);
    }

    public function editLockMessage(): ?string
    {
        return $this->editLockHolder === null ? null : EditLocks::message($this->editLockRecord(), $this->editLockHolder['name']);
    }

    /**
     * Every action but looking is refused without the lock, whichever button or panel asked for it.
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        if (! in_array($name, self::LOOKING_ACTIONS, true) && $this->refusesWhileLocked()) {
            return null;
        }

        return parent::mountAction($name, $arguments, $context);
    }

    /**
     * True (with a note saying who's editing) when this person mustn't change it. $verify re-checks the lock itself
     * before a save or publish, in case it lapsed and someone else took it since the last heartbeat.
     */
    protected function refusesWhileLocked(bool $verify = false): bool
    {
        if ($this->editLockHolder === null && (! $verify || $this->claimEditLock())) {
            return false;
        }

        Notification::make()
            ->title((string) $this->editLockMessage())
            ->body(__('You can look, but not change it until they close it.'))
            ->warning()
            ->send();

        return true;
    }

    private function claimEditLock(): bool
    {
        $user = auth()->user();
        $locks = app(EditLocks::class);

        if ($user !== null && $locks->claim($this->editLockRecord(), $user)) {
            $this->editLockHolder = null;

            return true;
        }

        $this->editLockHolder = $locks->heldByOther($this->editLockRecord(), $user)
            ?? ['id' => '', 'name' => __('Someone'), 'since' => now()->toIso8601String()];

        return false;
    }

    private function editLockRecord(): Model
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Model, 404);

        return $record;
    }
}
