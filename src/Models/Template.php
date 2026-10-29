<?php

namespace ScBudgetWeb\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ScBudgetWeb\SiteBuilder\Blocks\StoredBlocks;
use ScBudgetWeb\SiteBuilder\Pages\PageStatus;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * A Theme Builder template (Website › Theme builder): a header or footer (or a type the app adds) built from blocks,
 * used in place of the theme's standard one wherever it applies (Templates::for()).
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property bool $is_active
 * @property string $applies_to
 * @property array<int, int>|null $page_ids
 * @property array<int|string, mixed>|null $blocks
 * @property Carbon|null $updated_at
 */
class Template extends Model
{
    public const EVERYWHERE = 'everywhere';

    public const ONLY = 'only';

    public const EXCEPT = 'except';

    protected $table = 'site_builder_templates';

    protected $fillable = ['name', 'type', 'is_active', 'applies_to', 'page_ids', 'blocks'];

    protected $attributes = [
        'is_active' => true,
        'applies_to' => self::EVERYWHERE,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'page_ids' => 'array',
            'blocks' => StoredBlocks::class,
        ];
    }

    protected static function booted(): void
    {
        // Which template applies where is cached; any change to one makes that stale.
        static::saved(fn () => Templates::flush());
        static::deleted(fn () => Templates::flush());
    }

    /**
     * Plain slashes, so upload paths match a plain LIKE, as on pages (lesson 13).
     *
     * @param  mixed  $value
     * @param  int  $flags
     */
    protected function asJson($value, $flags = 0): string|false
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * An unsaved page holding the template's blocks (or $blocks, e.g. a draft), for rendering it and for the visual
     * builder's canvas.
     *
     * @param  array<int|string, mixed>|null  $blocks
     */
    public function previewPage(?array $blocks = null): Page
    {
        return (new Page)->forceFill([
            'title' => $this->name,
            'slug' => 'theme-builder-preview',
            'status' => PageStatus::Draft,
            'show_page_banner' => false,
            'noindex' => true,
            'blocks' => $blocks ?? $this->blocks ?? [],
        ]);
    }
}
