<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A stand-in for spatie/laravel-permission's Role, shaped like SaaSykit Tenancy's roles (is_tenant_role, tenant_id).
 */
class Role extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    public function givePermissionTo(array $permissions): void
    {
        $this->permissions()->syncWithoutDetaching(array_map(fn (Permission $permission): int => $permission->id, $permissions));
    }
}
