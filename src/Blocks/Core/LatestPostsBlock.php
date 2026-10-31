<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blog\Blog;

/**
 * The latest blog posts as cards, on any page or template, optionally from one category. Offered when the app has a
 * blog.
 */
class LatestPostsBlock extends PostCardsBlock
{
    public const MAX_POSTS = 12;

    public static function name(): string
    {
        return 'latest_posts';
    }

    public static function label(): string
    {
        return __('Latest posts');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedNewspaper;
    }

    public static function isOffered(): bool
    {
        return app(Blog::class)->isAvailable();
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
                Select::make('category_id')
                    ->label(__('From'))
                    ->options(fn (): array => app(Blog::class)->categories())
                    ->placeholder(__('Every category')),
            ]),
            static::partsField(),
        ];
    }

    protected static function posts(array $data): array
    {
        $count = is_numeric($data['count'] ?? null) ? max(1, min(self::MAX_POSTS, (int) $data['count'])) : 3;

        return app(Blog::class)->latest($count, is_numeric($data['category_id'] ?? null) ? (int) $data['category_id'] : null);
    }

    protected static function itemLabel(array $state): string
    {
        return filled($state['heading'] ?? null) && is_string($state['heading']) ? $state['heading'] : static::label();
    }
}
