<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use ScBudgetWeb\SiteBuilder\Pages\PagePolicy;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the page permissions and grants them to the admin role, when the app uses spatie/laravel-permission
     * (SaaSykit does). Existing installs never re-run their seeders, so this is the only place they're created.
     * On a fresh install the admin role doesn't exist yet; SaaSykit's RolesAndPermissionsSeeder then gives the
     * admin role every non-tenancy permission, including these.
     */
    public function up(): void
    {
        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');

        if (! is_string($permissionModel) || ! class_exists($permissionModel) || ! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            return;
        }

        $permissions = array_map(fn (string $name) => $permissionModel::findOrCreate($name), PagePolicy::permissions());

        if (is_string($roleModel) && class_exists($roleModel)) {
            $roleTable = config('permission.table_names.roles', 'roles');

            $roleModel::query()
                ->where('name', 'admin')
                // SaaSykit Tenancy also has a tenant "admin" role (and tenants' own roles), which must not get
                // site-wide permissions.
                ->when(Schema::hasColumn($roleTable, 'is_tenant_role'), fn ($query) => $query->where('is_tenant_role', false))
                ->when(Schema::hasColumn($roleTable, 'tenant_id'), fn ($query) => $query->whereNull('tenant_id'))
                // The same guard as the permissions, so the grant can't fail or land on another guard's "admin".
                ->when($permissions !== [] && Schema::hasColumn($roleTable, 'guard_name'), fn ($query) => $query->where('guard_name', $permissions[0]->guard_name))
                ->orderBy('id')
                ->first()
                ?->givePermissionTo($permissions);
        }

        $this->forgetCachedPermissions();
    }

    /**
     * Removes only the permissions this package creates under their standard names. An app that maps the page
     * abilities to its own permissions (e.g. "update settings") keeps them.
     */
    public function down(): void
    {
        $permissionModel = config('permission.models.permission');

        if (is_string($permissionModel) && class_exists($permissionModel)) {
            $permissionModel::query()
                ->whereIn('name', array_intersect(PagePolicy::permissions(), ['view pages', 'create pages', 'update pages', 'delete pages']))
                ->delete();
            $this->forgetCachedPermissions();
        }
    }

    private function forgetCachedPermissions(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
