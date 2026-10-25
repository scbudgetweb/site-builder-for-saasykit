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
                // SaaSykit Tenancy also has a tenant "admin" role, which must not get site-wide permissions.
                ->when(Schema::hasColumn($roleTable, 'is_tenant_role'), fn ($query) => $query->where('is_tenant_role', false))
                ->first()
                ?->givePermissionTo($permissions);
        }

        $this->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionModel = config('permission.models.permission');

        if (is_string($permissionModel) && class_exists($permissionModel)) {
            $permissionModel::query()->whereIn('name', PagePolicy::permissions())->delete();
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
