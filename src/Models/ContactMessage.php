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
 * @property array<int, array{label: string, type: string, value: string|array<int, string>}>|null $fields
 * @property string|null $page_url
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class ContactMessage extends Model
{
    use MassPrunable;

    protected $table = 'site_builder_contact_messages';

    protected $fillable = ['page_id', 'form_name', 'name', 'email', 'phone', 'subject', 'message', 'fields', 'page_url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'fields' => 'array'];
    }

    /**
     * Each answer to show, in the form's order, as label => text (Checkboxes joined with commas). Messages from before
     * 1.7.2 have no list, so it's made from the standard columns.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function answers(bool $withEmpty = true): array
    {
        $saved = is_array($this->fields) ? $this->fields : [
            ['label' => __('Name'), 'value' => $this->name],
            ['label' => __('Email address'), 'value' => $this->email],
            ['label' => __('Phone number'), 'value' => $this->phone],
            ['label' => __('Subject'), 'value' => $this->subject],
            ['label' => __('Message'), 'value' => $this->message],
        ];
        $answers = [];

        foreach ($saved as $answer) {
            if (! is_array($answer) || ! is_string($answer['label'] ?? null)) {
                continue;
            }

            $value = $answer['value'] ?? null;
            $text = is_array($value) ? implode(', ', array_filter($value, 'is_string')) : (is_string($value) ? $value : '');

            if ($withEmpty || $text !== '') {
                $answers[] = ['label' => $answer['label'], 'value' => $text];
            }
        }

        return $answers;
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
