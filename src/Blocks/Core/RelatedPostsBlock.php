<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: more posts to read after this one, from its category first, then the latest others.
 */
class RelatedPostsBlock extends PostCardsBlock
{
    public const MAX_POSTS = 12;

    public static function name(): string
    {
        return 'related_posts';
    }

    public static function label(): string
    {
        return __('Related posts');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedNewspaper;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('count')
                    ->label(__('How many'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(self::MAX_POSTS)
                    ->default(3),
                Toggle::make('same_category_first')
                    ->label(__('From the same category first'))
                    ->helperText(__('Then the latest others, to make up the number.'))
                    ->default(true)
                    ->inline(false),
            ]),
            static::partsField(),
        ];
    }

    protected static function posts(array $data): array
    {
        $blog = app(Blog::class);
        $post = $blog->post();
        $count = is_numeric($data['count'] ?? null) ? max(1, min(self::MAX_POSTS, (int) $data['count'])) : 3;

        return $post === null ? [] : $blog->related($post, $count, (bool) ($data['same_category_first'] ?? true));
    }

    protected static function itemLabel(array $state): string
    {
        return filled($state['heading'] ?? null) && is_string($state['heading']) ? $state['heading'] : static::label();
    }
}
