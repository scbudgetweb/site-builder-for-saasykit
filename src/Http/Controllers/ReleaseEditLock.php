<?php

namespace ScBudgetWeb\SiteBuilder\Http\Controllers;

use Illuminate\Http\Response;
use ScBudgetWeb\SiteBuilder\Pages\EditLocks;

/**
 * Frees an edit lock when its editor is closed (the signed link from HoldsEditLock::editLockReleaseUrl()).
 */
class ReleaseEditLock
{
    public function __invoke(EditLocks $locks, string $kind, string $id, string $user): Response
    {
        $model = EditLocks::modelFor($kind);
        $record = $model === null ? null : $model::query()->withoutGlobalScopes()->find($id);

        if ($record !== null) {
            $locks->release($record, $user);
        }

        return response()->noContent();
    }
}
