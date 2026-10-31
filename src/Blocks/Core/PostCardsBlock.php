<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;
use ScBudgetWeb\SiteBuilder\Blocks\Design\ItemParts;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blog\BlogPost;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Blog posts as cards (Related posts, and the blog archive's lists): each with its image, date and category, title
 * (the link to the post) and description, every part optional, in columns. Their look is Design › Items, as Image
 * cards have. The posts come from the subclass (posts()); with none, Content › When empty decides.
 */
abstract class PostCardsBlock extends PageBlock
{
    public const PARTS = ['image' => 'Image', 'date' => 'Date', 'category' => 'Category', 'excerpt' => 'Description'];

    /**
     * The posts to show, newest first.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, BlogPost>
     */
    abstract protected static function posts(array $data): array;

    /**
     * What the cards show, for the block's content.
     */
    protected static function partsField(): CheckboxList
    {
        return CheckboxList::make('show')
            ->label(__('Each card shows'))
            ->hintIcon(self::HELP_ICON, __('Its title, which links to the post, and any of these.'))
            ->options(array_map(fn (string $label): string => __($label), self::PARTS))
            ->default(array_keys(self::PARTS))
            ->in(array_keys(self::PARTS))
            ->columns(2);
    }

    protected static function styleToggleTitle(): string
    {
        return __('Images & card layout');
    }

    protected static function styleFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('image_shape')
                    ->label(__('Image shape'))
                    ->hintIcon(self::HELP_ICON, __('Every card\'s image is trimmed to this shape, so the cards line up.'))
                    ->options(array_map(fn (array $shape): string => __($shape[0]), ImageCardsBlock::IMAGE_SHAPES))
                    ->default('wide')
                    ->selectablePlaceholder(false),
            ]),
            static::imageStyleFieldset(),
            Group::make()
                ->statePath('card')
                ->columns(2)
                ->schema([
                    Toggle::make('background')
                        ->label(__('Card background'))
                        ->hintIcon(self::HELP_ICON, __('A background and border make each card a box. Turn both off for a lighter look. Their colours, shadow and text are under Design › Items.'))
                        ->default(true),
                    Toggle::make('border')->label(__('Card border'))->default(true),
                ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [Grid::make(2)->schema([static::columnsSelect(3)])];
    }

    /** The posts change without the page being saved, so the block is drawn on each request. */
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
        return ($viewData['cards'] ?? []) === [];
    }

    /**
     * The cards and their look. A subclass adds its own data (e.g. pagination) to this.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, mixed>  $posts
     * @return array<string, mixed>
     */
    protected static function cardsData(array $data, array $posts): array
    {
        $show = is_array($data['show'] ?? null) ? $data['show'] : array_keys(self::PARTS);
        $style = CardStyle::resolve(['icon_position' => 'top', ...(is_array($data['card'] ?? null) ? $data['card'] : [])], $data['design']['items'] ?? null);

        return [
            'cards' => array_map(fn (BlogPost $post): array => [
                'title' => $post->title,
                'url' => $post->url,
                'imageUrl' => in_array('image', $show, true) ? $post->imageUrl : null,
                'date' => in_array('date', $show, true) ? $post->date() : '',
                'datetime' => $post->isPublished ? $post->publishedAt?->toDateString() : null,
                'category' => in_array('category', $show, true) ? $post->category : '',
                'excerpt' => in_array('excerpt', $show, true) ? $post->excerpt : '',
            ], array_values(array_filter($posts, fn (mixed $post): bool => $post instanceof BlogPost))),
            'columns' => static::columns($data),
            'imageShape' => ImageCardsBlock::IMAGE_SHAPES[static::pick($data['image_shape'] ?? null, ImageCardsBlock::IMAGE_SHAPES, 'wide')][1],
            'imageStyle' => static::imageCornerStyle($data),
            'imageBorderStyle' => BorderShadow::style($data, 'image_'),
            'style' => $style,
            // As Image cards: the box on the card, its padding on the body, so the image runs edge to edge.
            'cardClass' => trim('relative flex h-full flex-col overflow-hidden '.implode(' ', array_diff(
                explode(' ', $style['card']),
                ['flex', 'flex-col', 'flex-row', 'items-start', 'items-center', 'text-center', 'gap-4', 'p-6'],
            ))),
            'bodyClass' => 'flex flex-1 flex-col '.($style['hasBox'] ? 'p-6' : 'pt-5'),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return static::cardsData($data, static::posts($data));
    }

    public static function view(): string
    {
        return 'site-builder::blocks.post-cards';
    }

    public static function itemParts(): ItemParts
    {
        return ItemParts::make(__('Cards'))
            ->text('meta', __('Date and category'))
            ->text('title', __('Title text'))
            ->text('text', __('Description'));
    }
}
