<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Contracts\Auth\Access\Authorizable;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Admin › Website › Pages. Each ability maps to a permission name in config site-builder.permissions.pages
 * (SaaSykit: spatie/laravel-permission, created and granted to admins by the package's migration).
 * Restoring and deleting for good from the bin need the delete permission.
 */
class PagePolicy
{
    /**
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_values(array_filter((array) config('site-builder.permissions.pages', []), 'is_string'));
    }

    public function viewAny(Authorizable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authorizable $user, Page $page): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authorizable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authorizable $user, Page $page): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(Authorizable $user, Page $page): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(Authorizable $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(Authorizable $user, Page $page): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restoreAny(Authorizable $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDelete(Authorizable $user, Page $page): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDeleteAny(Authorizable $user): bool
    {
        return $this->allows($user, 'delete');
    }

    private function allows(Authorizable $user, string $ability): bool
    {
        $permission = config("site-builder.permissions.pages.{$ability}");

        return is_string($permission) && $user->can($permission);
    }
}
