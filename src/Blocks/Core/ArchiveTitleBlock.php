<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog archive template: the archive's title (the blog's own, or the category's name), with an optional line under it,
 * drawn as the Heading block draws a heading.
 */
class ArchiveTitleBlock extends PageBlock
{
    public static function name(): string
    {
        return 'archive_title';
    }

    public static function label(): string
    {
        return __('Archive title');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedH1;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_ARCHIVE];
    }

    public static function defaultSpacing(): string
    {
        return 'small';
    }

    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    /** The line under the title is body text. */
    public static function hasBodyText(): bool
    {
        return true;
    }

    public static function view(): string
    {
        return 'site-builder::blocks.archive-title';
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('blog_title')
                    ->label(__('The blog\'s title'))
                    ->hintIcon(self::HELP_ICON, __('Shown on the blog\'s own page. A category\'s page shows the category\'s name.'))
                    ->placeholder(__('Blog'))
                    ->maxLength(150),
                Select::make('level')
                    ->label(__('Level'))
                    ->hintIcon(self::HELP_ICON, __('The archive\'s title is usually its page\'s main heading (H1).'))
                    ->options(array_map(fn (string $label): string => __($label), HeadingBlock::LEVELS))
                    ->default('h1')
                    ->selectablePlaceholder(false),
            ]),
            Textarea::make('intro')
                ->label(__('Line under it'))
                ->helperText(__('Optional. {{ archive_title }} shows the category\'s name, or "Blog" on the blog\'s own page.'))
                ->rows(2)
                ->maxLength(500),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('align')
                ->label(__('Alignment'))
                ->options(array_map(fn (string $label): string => __($label), HeadingBlock::ALIGNMENTS))
                ->default('center')
                ->selectablePlaceholder(false),
        ];
    }

    /** It shows the archive being viewed, so it's drawn on each request. */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return app(Blog::class)->isArchive();
    }

    public static function viewData(array $data, Page $page): array
    {
        $blog = app(Blog::class);
        $blogTitle = is_string($data['blog_title'] ?? null) && trim($data['blog_title']) !== '' ? trim($data['blog_title']) : null;

        return [
            'heading' => $blog->categoryId() === null ? ($blogTitle ?? $blog->archiveTitle() ?? '') : ($blog->archiveTitle() ?? ''),
            'intro' => is_string($data['intro'] ?? null) ? trim($data['intro']) : '',
            'level' => static::pick($data['level'] ?? null, HeadingBlock::LEVELS, 'h1'),
            'align' => static::pick($data['align'] ?? null, HeadingBlock::ALIGNMENTS, 'center'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
