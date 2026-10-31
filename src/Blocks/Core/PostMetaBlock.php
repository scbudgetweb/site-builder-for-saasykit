<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: the post's date, author and category on a line, each optional, the category linking to its
 * posts.
 */
class PostMetaBlock extends PageBlock
{
    public const PARTS = ['date' => 'Date', 'author' => 'Author', 'category' => 'Category'];

    public const CATEGORY_LOOKS = ['pill' => 'A pill', 'text' => 'A link'];

    public static function name(): string
    {
        return 'post_meta';
    }

    public static function label(): string
    {
        return __('Post details');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedCalendarDays;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    public static function defaultSpacing(): string
    {
        return 'small';
    }

    /** Its line is body text (.block-text) of its own. */
    public static function hasBodyText(): bool
    {
        return true;
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                CheckboxList::make('show')
                    ->label(__('Show'))
                    ->options(array_map(fn (string $label): string => __($label), self::PARTS))
                    ->default(array_keys(self::PARTS))
                    ->in(array_keys(self::PARTS)),
                Select::make('category_look')
                    ->label(__('Category as'))
                    ->options(array_map(fn (string $label): string => __($label), self::CATEGORY_LOOKS))
                    ->default('pill')
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

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return static::viewData($data, new Page)['parts'] !== [];
    }

    public static function viewData(array $data, Page $page): array
    {
        $post = app(Blog::class)->post();
        $show = is_array($data['show'] ?? null) ? $data['show'] : array_keys(self::PARTS);

        $parts = $post === null ? [] : array_filter([
            'date' => in_array('date', $show, true) && $post->date() !== '' ? ['text' => $post->date(), 'datetime' => $post->isPublished ? $post->publishedAt?->toDateString() : null] : null,
            'author' => in_array('author', $show, true) && $post->author !== '' ? ['text' => $post->author] : null,
            'category' => in_array('category', $show, true) && $post->category !== '' ? ['text' => $post->category, 'url' => $post->categoryUrl] : null,
        ]);

        return [
            'parts' => $parts,
            'categoryLook' => static::pick($data['category_look'] ?? null, self::CATEGORY_LOOKS, 'pill'),
            'align' => static::pick($data['align'] ?? null, HeadingBlock::ALIGNMENTS, 'left'),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
