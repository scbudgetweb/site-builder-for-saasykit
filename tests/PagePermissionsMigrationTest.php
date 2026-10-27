<?php

namespace ScBudgetWeb\SiteBuilder\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Permissions\Permission;
use ScBudgetWeb\SiteBuilder\Tests\Fixtures\Permissions\Role;

/**
 * The page permissions migration, against Spatie-shaped roles like SaaSykit Tenancy's.
 */
class PagePermissionsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->boolean('is_tenant_role')->default(false);
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        config([
            'permission.models.permission' => Permission::class,
            'permission.models.role' => Role::class,
            'permission.table_names.permissions' => 'permissions',
            'permission.table_names.roles' => 'roles',
        ]);
    }

    public function test_only_the_site_wide_admin_role_gets_the_page_permissions(): void
    {
        $tenantAdmin = Role::query()->create(['name' => 'admin', 'guard_name' => 'web', 'is_tenant_role' => true, 'tenant_id' => 7]);
        $tenantsOwnAdmin = Role::query()->create(['name' => 'admin', 'guard_name' => 'web', 'is_tenant_role' => false, 'tenant_id' => 8]);
        $apiAdmin = Role::query()->create(['name' => 'admin', 'guard_name' => 'api']);
        $siteAdmin = Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

        $this->migration()->up();

        $this->assertEqualsCanonicalizing(['view pages', 'create pages', 'update pages', 'delete pages'], $siteAdmin->permissions()->pluck('name')->all());

        foreach ([$tenantAdmin, $tenantsOwnAdmin, $apiAdmin] as $role) {
            $this->assertSame(0, $role->permissions()->count());
        }
    }

    public function test_rolling_back_keeps_an_apps_own_permission_mapped_to_a_page_ability(): void
    {
        config(['site-builder.permissions.pages.update' => 'update settings']);
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(['update settings'], Permission::query()->pluck('name')->all());
    }

    private function migration(): Migration
    {
        return require __DIR__.'/../database/migrations/2026_10_25_000001_create_site_builder_page_permissions.php';
    }
}
