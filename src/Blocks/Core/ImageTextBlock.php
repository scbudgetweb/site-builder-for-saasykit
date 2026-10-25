<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * An image with a heading, text and buttons. Without an image it's a heading, text and buttons.
 */
class ImageTextBlock extends PageBlock
{
    public const BUTTON_POSITIONS = ['after_text', 'before_text', 'below_image', 'above_image'];

    /** Saved as "image_side": left and right sit side by side (stacked on phones); above and below always stack. */
    public const IMAGE_POSITIONS = [
        'left' => 'Left of the text',
        'right' => 'Right of the text',
        'above' => 'Above the text',
        'below' => 'Below the text',
    ];

    public static function name(): string
    {
        return 'image_text';
    }

    public static function label(): string
    {
        return __('Image + text');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedViewColumns;
    }

    public static function headingSettings(): array
    {
        return ['decoration', 'size'];
    }

    protected static function fields(): array
    {
        return [
            static::imageUpload('image', __('Image'))
                ->helperText(__('Optional. JPG, PNG or WebP, up to 2 MB.')),
            TextInput::make('alt')
                ->label(__('Alternative text'))
                ->helperText(__('Describes the image for people who can\'t see it.'))
                ->requiredWith('image')
                ->maxLength(200),
            TextInput::make('heading')->label(__('Heading'))->maxLength(150)->live(onBlur: true),
            static::richText('content', __('Text')),
            static::buttonsRepeater(2),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            static::imageStyleFieldset(__('Automatic (site style)')),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Select::make('image_side')
                ->label(__('Image position'))
                ->options(array_map(fn (string $label): string => __($label), self::IMAGE_POSITIONS))
                ->default('left')
                ->selectablePlaceholder(false),
            static::buttonPositionSelect(self::BUTTON_POSITIONS),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $imageUrl = static::imageUrl($data['image'] ?? null);
        $buttonPosition = static::buttonPosition($data, self::BUTTON_POSITIONS);

        return [
            'imageUrl' => $imageUrl,
            'imagePosition' => static::pick($data['image_side'] ?? null, self::IMAGE_POSITIONS, 'left'),
            // With no image, the image positions mean the text's.
            'buttonPosition' => $imageUrl ? $buttonPosition : (['above_image' => 'before_text', 'below_image' => 'after_text'][$buttonPosition] ?? $buttonPosition),
            'imageStyle' => static::imageStyle($data, defaultShadow: 'site'),
            'html' => static::html($data['content'] ?? null),
            'buttons' => static::validButtons($data['buttons'] ?? null),
        ];
    }
}
