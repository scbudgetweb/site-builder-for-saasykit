<?php

namespace ScBudgetWeb\SiteBuilder\Tests\Fixtures\Permissions;

use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for spatie/laravel-permission's Permission (which the package doesn't depend on), with the one method the
 * page permissions migration uses.
 */
class Permission extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function findOrCreate(string $name): self
    {
        return static::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
