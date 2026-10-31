<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Blog\BlogPost;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog archive template: the archive's posts (the whole blog's, or the category's) as cards, a page at a time, with
 * links to the other pages.
 */
class PostListBlock extends PostCardsBlock
{
    public const DEFAULT_PER_PAGE = 12;

    public const MAX_PER_PAGE = 48;

    public static function name(): string
    {
        return 'post_list';
    }

    public static function label(): string
    {
        return __('Post list');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedQueueList;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_ARCHIVE];
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('per_page')
                    ->label(__('Posts per page'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_PER_PAGE)
                    ->default(self::DEFAULT_PER_PAGE),
                Toggle::make('paginate')
                    ->label(__('Links to more pages'))
                    ->helperText(__('Off: only the latest posts show.'))
                    ->default(true)
                    ->inline(false),
            ]),
            static::partsField(),
        ];
    }

    protected static function posts(array $data): array
    {
        return self::paginator($data)->items();
    }

    public static function viewData(array $data, Page $page): array
    {
        $posts = self::paginator($data);

        return [
            ...static::cardsData($data, $posts->items()),
            'pagination' => ($data['paginate'] ?? true) && $posts->hasPages()
                ? new HtmlString($posts->onEachSide(1)->links('site-builder::blocks.partials.pagination')->toHtml())
                : null,
        ];
    }

    /**
     * This page of posts; always the first (the latest) without links to the others.
     *
     * @param  array<string, mixed>  $data
     * @return LengthAwarePaginator<int, BlogPost>
     */
    private static function paginator(array $data): LengthAwarePaginator
    {
        $perPage = is_numeric($data['per_page'] ?? null) ? max(1, min(self::MAX_PER_PAGE, (int) $data['per_page'])) : self::DEFAULT_PER_PAGE;

        return app(Blog::class)->archivePosts($perPage, ($data['paginate'] ?? true) ? null : 1);
    }

    protected static function itemLabel(array $state): string
    {
        return filled($state['heading'] ?? null) && is_string($state['heading']) ? $state['heading'] : static::label();
    }
}
