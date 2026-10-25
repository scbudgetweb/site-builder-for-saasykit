<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Contracts\Auth\Access\Authorizable;
use ScBudgetWeb\SiteBuilder\Models\SavedBlock;

/**
 * Admin › Website › Block library. The library is part of building pages, so it uses the page permissions
 * (config site-builder.permissions.pages).
 */
class SavedBlockPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authorizable $user, SavedBlock $savedBlock): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authorizable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authorizable $user, SavedBlock $savedBlock): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(Authorizable $user, SavedBlock $savedBlock): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->allows($user, 'delete');
    }

    private function allows(Authorizable $user, string $ability): bool
    {
        $permission = config("site-builder.permissions.pages.{$ability}");

        return is_string($permission) && $user->can($permission);
    }
}
