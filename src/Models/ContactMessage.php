<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message a visitor sent through a Contact form block (Website › Contact messages). Kept for
 * site-builder.contact.keep_days, then pruned (schedule model:prune); 0 keeps them. No IP address is kept.
 *
 * @property int $id
 * @property int|null $page_id
 * @property string|null $form_name
 * @property string|null $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property string|null $page_url
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class ContactMessage extends Model
{
    use MassPrunable;

    protected $table = 'site_builder_contact_messages';

    protected $fillable = ['page_id', 'form_name', 'name', 'email', 'phone', 'subject', 'message', 'page_url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('site-builder.contact.keep_days', 365);

        return $days > 0
            ? static::query()->where('created_at', '<', now()->subDays($days))
            : static::query()->whereRaw('1 = 0');
    }
}
