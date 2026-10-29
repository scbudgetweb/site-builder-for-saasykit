<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * A mailing list service (Brevo first) that sign-up forms add people to (Website › Mailing lists). One can be in use at
 * a time, and it only takes sign-ups once it has an API key and a default list. The API key is encrypted and only
 * ever set here, never in a block.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property string|null $api_key
 * @property string|null $list_id
 * @property string|null $list_name
 * @property bool $double_opt_in
 * @property string|null $double_opt_in_template_id
 * @property string|null $double_opt_in_redirect_url
 */
class MailingListProvider extends Model
{
    public const BREVO = 'brevo';

    protected $table = 'site_builder_mailing_list_providers';

    protected $fillable = [
        'name',
        'is_active',
        'api_key',
        'list_id',
        'list_name',
        'double_opt_in',
        'double_opt_in_template_id',
        'double_opt_in_redirect_url',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
            'double_opt_in' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // One at a time: turning one on turns the others off.
        static::saved(function (MailingListProvider $provider): void {
            if ($provider->is_active && ($provider->wasChanged('is_active') || $provider->wasRecentlyCreated)) {
                static::query()->whereKeyNot($provider->getKey())->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    /**
     * The service taking sign-ups: in use, with an API key and a default list. Null hides sign-up forms. Before the
     * table is migrated (just after updating the package), there's none.
     */
    public static function active(): ?self
    {
        try {
            return static::query()
                ->where('is_active', true)
                ->whereNotNull('api_key')
                ->whereNotNull('list_id')
                ->first();
        } catch (QueryException) {
            return null;
        }
    }

    public function isConnected(): bool
    {
        return filled($this->api_key);
    }
}
