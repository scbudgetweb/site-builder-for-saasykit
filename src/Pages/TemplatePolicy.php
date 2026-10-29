<?php

namespace ScBudgetWeb\SiteBuilder\Pages;

use Illuminate\Contracts\Auth\Access\Authorizable;
use ScBudgetWeb\SiteBuilder\Models\Template;

/**
 * Admin › Website › Theme builder. Templates are built like pages, so they use the page permissions
 * (config site-builder.permissions.pages), as the block library does.
 */
class TemplatePolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authorizable $user, Template $template): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authorizable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authorizable $user, Template $template): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(Authorizable $user, Template $template): bool
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
