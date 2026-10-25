<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\CardStyle;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Cards with an image (or an icon), a title, text and a button, every part optional. With more cards
 * than columns they become a carousel (arrows and swipe), which can move on by itself.
 */
class ImageCardsBlock extends PageBlock
{
    public const MEDIA = [
        'image' => 'Image',
        'icon' => 'Icon',
        'none' => 'Nothing',
    ];

    /** Image shape => [label, classes]. */
    public const IMAGE_SHAPES = [
        'photo' => ['Photo (3:2)', 'aspect-[3/2]'],
        'wide' => ['Wide (16:9)', 'aspect-video'],
        'classic' => ['Classic (4:3)', 'aspect-[4/3]'],
        'square' => ['Square', 'aspect-square'],
        'portrait' => ['Portrait (3:4)', 'aspect-[3/4]'],
    ];

    /** Columns => carousel card widths: most of the screen on phones, two from tablets, then the columns. */
    private const CAROUSEL_WIDTHS = [
        2 => 'w-[85%] sm:w-[calc((100%-1.5rem)/2)]',
        3 => 'w-[85%] sm:w-[calc((100%-1.5rem)/2)] lg:w-[calc((100%-3rem)/3)]',
        4 => 'w-[85%] sm:w-[calc((100%-1.5rem)/2)] lg:w-[calc((100%-4.5rem)/4)]',
    ];

    public static function name(): string
    {
        return 'image_cards';
    }

    public static function label(): string
    {
        return __('Image cards');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedRectangleStack;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Repeater::make('cards')
                ->label(__('Cards'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('media')
                            ->label(__('At the top'))
                            ->options(array_map(fn (string $label): string => __($label), self::MEDIA))
                            ->default('image')
                            ->selectablePlaceholder(false)
                            ->live(),
                        TextInput::make('title')->label(__('Title'))->maxLength(100)->live(onBlur: true),
                    ]),
                    static::imageUpload('image', __('Image'))
                        ->visible(fn (Get $get): bool => $get('media') === 'image'),
                    TextInput::make('alt')
                        ->label(__('Alternative text'))
                        ->helperText(__('Describes the image for people using screen readers.'))
                        ->requiredWith('image')
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => $get('media') === 'image'),
                    static::iconSelect()
                        ->visible(fn (Get $get): bool => $get('media') === 'icon'),
                    Textarea::make('text')->label(__('Text'))->rows(3)->maxLength(500),
                    static::buttonsRepeater(1),
                ])
                ->minItems(1)
                ->maxItems(24)
                ->defaultItems(3)
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? $state['title'] : __('Card'))
                ->addActionLabel(__('Add card')),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('image_shape')
                    ->label(__('Image shape'))
                    ->options(array_map(fn (array $shape): string => __($shape[0]), self::IMAGE_SHAPES))
                    ->default('photo')
                    ->selectablePlaceholder(false),
            ]),
            static::imageStyleFieldset(),
            CardStyle::fieldset(),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Grid::make(2)->schema([
                static::columnsSelect(3),
                Toggle::make('carousel')
                    ->label(__('Carousel when there are more cards than columns'))
                    ->helperText(__('Off: extra cards wrap onto new rows.'))
                    ->default(true)
                    ->inline(false)
                    ->live(),
                Toggle::make('autoplay')
                    ->label(__('Carousel moves on by itself'))
                    ->helperText(__('Every few seconds, back to the start after the last card. It pauses while the mouse is over it, and stays still for visitors who turn off animations.'))
                    ->default(false)
                    ->inline(false)
                    ->visible(fn (Get $get): bool => (bool) $get('carousel')),
            ]),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $columns = static::columns($data);

        $cards = collect($data['cards'] ?? [])
            ->filter(fn (mixed $card): bool => is_array($card))
            ->map(function (array $card): array {
                $media = static::pick($card['media'] ?? null, self::MEDIA, 'image');

                return [
                    'imageUrl' => $media === 'image' ? static::imageUrl($card['image'] ?? null) : null,
                    'alt' => (string) ($card['alt'] ?? ''),
                    'icon' => $media === 'icon' && is_string($card['icon'] ?? null) ? $card['icon'] : null,
                    'title' => trim((string) ($card['title'] ?? '')),
                    'text' => trim((string) ($card['text'] ?? '')),
                    'buttons' => is_array($card['buttons'] ?? null) ? $card['buttons'] : [],
                ];
            })
            ->values()
            ->all();

        $isCarousel = (bool) ($data['carousel'] ?? true) && count($cards) > $columns;

        return [
            'cards' => $cards,
            'columns' => $columns,
            'isCarousel' => $isCarousel,
            'autoplay' => $isCarousel && (bool) ($data['autoplay'] ?? false),
            'carouselWidth' => self::CAROUSEL_WIDTHS[$columns],
            'imageShape' => self::IMAGE_SHAPES[static::pick($data['image_shape'] ?? null, self::IMAGE_SHAPES, 'photo')][1],
            // Corners only on cards without a box (the image runs edge to edge in a box); border and shadow always.
            'imageStyle' => static::imageCornerStyle($data),
            'imageBorderStyle' => BorderShadow::style($data, 'image_'),
            'style' => $style = CardStyle::resolve($data['card'] ?? null),
            // The card's box (background, border, corners, shadow, text colour) without CardStyle's layout,
            // as the image sits edge to edge on top and the layout goes on the card's body.
            'cardClass' => trim('flex h-full flex-col overflow-hidden '.implode(' ', array_diff(
                explode(' ', $style['card']),
                ['flex', 'flex-col', 'flex-row', 'items-start', 'items-center', 'text-center', 'gap-4', 'p-6'],
            ))),
            'bodyClass' => implode(' ', array_filter([
                'flex flex-1',
                $style['isRow'] ? 'flex-row items-start gap-4' : 'flex-col',
                $style['isCentered'] ? 'items-center text-center' : '',
                $style['hasBox'] ? 'p-6' : 'pt-5',
            ])),
            // Buttons suit the card's own background when it has one, otherwise the block's.
            'cardIsDark' => str_contains($style['card'], 'text-white') ? true : (str_contains($style['card'], 'text-gray-900') ? false : null),
        ];
    }
}
