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
 * Blog post template: the post's image (the first in Blog › Posts › Images), as it is or trimmed to a shape. Nothing
 * shows for a post without one.
 */
class PostImageBlock extends PageBlock
{
    /** Shape => [label, classes]: as uploaded, or one of Image cards' shapes. */
    public const SHAPES = [
        'natural' => ['As uploaded', 'h-auto'],
        ...ImageCardsBlock::IMAGE_SHAPES,
    ];

    public static function name(): string
    {
        return 'post_image';
    }

    public static function label(): string
    {
        return __('Featured image');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    public static function hasBodyText(): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('shape')
                    ->label(__('Shape'))
                    ->hintIcon(self::HELP_ICON, __('Trimmed to a shape, every post\'s image lines up the same, whatever was uploaded.'))
                    ->options(array_map(fn (array $shape): string => __($shape[0]), self::SHAPES))
                    ->default('natural')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    protected static function styleFields(): array
    {
        return [static::imageStyleFieldset()];
    }

    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function isVisible(array $data): bool
    {
        return app(Blog::class)->post()?->imageUrl !== null;
    }

    public static function viewData(array $data, Page $page): array
    {
        $post = app(Blog::class)->post();

        return [
            'imageUrl' => $post?->imageUrl,
            'alt' => $post->title ?? '',
            'shape' => self::SHAPES[static::pick($data['shape'] ?? null, self::SHAPES, 'natural')][1],
            'imageStyle' => static::imageStyle($data),
        ];
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
