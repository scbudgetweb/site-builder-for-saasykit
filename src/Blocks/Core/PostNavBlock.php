<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\Blog;
use ScBudgetWeb\SiteBuilder\Blog\BlogPost;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Templates\Templates;

/**
 * Blog post template: links to the post before this one and the one after it, by date,
 * each with a label and the post's title, and optionally its image. With neither, Content › When empty decides.
 */
class PostNavBlock extends PageBlock
{
    public static function name(): string
    {
        return 'post_nav';
    }

    public static function label(): string
    {
        return __('Previous and next posts');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedArrowsRightLeft;
    }

    public static function templateTypes(): ?array
    {
        return [Templates::BLOG_POST];
    }

    public static function hasBodyText(): bool
    {
        return false;
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Links'))
            ->text('meta', __('Label'))
            ->text('title', __('Post title'));
    }

    protected static function fields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('previous_label')
                    ->label(__('Previous link\'s label'))
                    ->placeholder(__('Previous post'))
                    ->maxLength(60),
                TextInput::make('next_label')
                    ->label(__('Next link\'s label'))
                    ->placeholder(__('Next post'))
                    ->maxLength(60),
                Toggle::make('same_category')
                    ->label(__('Only posts in the same category'))
                    ->default(false),
                Toggle::make('show_image')
                    ->label(__('Show each post\'s image'))
                    ->default(false),
            ]),
        ];
    }

    /** It shows the posts either side of the one being viewed, so it's drawn on each request. */
    public static function isCacheable(array $data): bool
    {
        return false;
    }

    public static function pullsContent(): bool
    {
        return true;
    }

    public static function isEmpty(array $data, array $viewData): bool
    {
        return ($viewData['links'] ?? []) === [];
    }

    public static function viewData(array $data, Page $page): array
    {
        $post = app(Blog::class)->post();
        $adjacent = $post === null ? ['previous' => null, 'next' => null] : app(Blog::class)->adjacent($post, (bool) ($data['same_category'] ?? false));
        $showsImage = (bool) ($data['show_image'] ?? false);
        $links = [];

        foreach (['previous' => __('Previous post'), 'next' => __('Next post')] as $direction => $standardLabel) {
            $label = is_string($data["{$direction}_label"] ?? null) && trim($data["{$direction}_label"]) !== '' ? trim($data["{$direction}_label"]) : $standardLabel;

            if ($adjacent[$direction] instanceof BlogPost) {
                $links[$direction] = [
                    'label' => $label,
                    'title' => $adjacent[$direction]->title,
                    'url' => $adjacent[$direction]->url,
                    'imageUrl' => $showsImage ? $adjacent[$direction]->imageUrl : null,
                ];
            }
        }

        return ['links' => $links];
    }

    public static function view(): string
    {
        return 'site-builder::blocks.post-nav';
    }

    protected static function itemLabel(array $state): string
    {
        return static::label();
    }
}
