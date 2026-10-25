<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One saved setting (Branding, Navigation, Legal pages…). Read through Settings, never directly.
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $table = 'site_builder_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
