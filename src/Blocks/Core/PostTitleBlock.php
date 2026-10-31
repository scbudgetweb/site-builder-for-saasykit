<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: the post's title, drawn as the Heading block draws a heading (its level, alignment and Design ›
 * Heading text).
 */
class PostTitleBlock extends PageBlock
{
    public static function name(): string
    {
        return 'post_title';
    }

    public static function label(): string
    {
        return __('Post title');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedH1;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    public static function defaultSpacing(): string
    {
        return 'small';
    }

    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    public static function hasBodyText(): bool
    {
        return false;
    }

    public static function view(): string
    {
        return 'site-builder::blocks.heading';
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('level')
                    ->label(__('Level'))
                    ->hintIcon(self::HELP_ICON, __('A post\'s title is usually its page\'s main heading (H1).'))
                    ->options(array_map(fn (string $label): string => __($label), HeadingBlock::LEVELS))
                    ->default('h1')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(array_map(fn (string $label): string => __($label), HeadingBlock::ALIGNMENTS))
                ->default('left')
                ->selectablePlaceholder(false),
        ];
    }

    /** It shows the post being viewed, so it's drawn on each request. */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return app(Blog::class)->post() !== null;
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'heading' => app(Blog::class)->post()->title ?? '',
            'level' => static::pick($data['level'] ?? null, HeadingBlock::LEVELS, 'h1'),
            'align' => static::pick($data['align'] ?? null, HeadingBlock::ALIGNMENTS, 'left'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
