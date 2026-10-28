<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

class ImageBlock extends PageBlock
{
    public static function name(): string
    {
        return 'image';
    }

    public static function label(): string
    {
        return __('Image');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function headingSettings(): array
    {
        return [];
    }

    public static function hasBodyText(): bool
    {
        return false;
    }

    protected static function fields(): array
    {
        return [
            static::imageUpload('image', __('Image'))->required(),
            TextInput::make('alt')
                ->label(__('Alternative text'))
                ->helperText(__('Describes the image for people using screen readers, and for search engines.'))
                ->required()
                ->maxLength(200),
            TextInput::make('caption')->label(__('Caption'))->maxLength(200),
            TextInput::make('link')
                ->label(__('Link (optional)'))
                ->hintIcon(self::HELP_ICON, __('Where clicking the image goes: a page on this site (/about-us), a part of this page (#pricing) or a full web address (https://…).'))
                ->maxLength(500)
                ->regex('/^(https?:\/\/|\/|#)/i')
                ->validationMessages(['regex' => __('Start the link with https://, / or #.')]),
            static::buttonsRepeater(2),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            static::buttonPositionSelect(['below_image', 'above_image']),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            static::imageStyleFieldset(),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'imageUrl' => static::imageUrl($data['image'] ?? null),
            'imageStyle' => static::imageStyle($data),
            'buttonPosition' => static::buttonPosition($data, ['below_image', 'above_image']),
        ];
    }
}
