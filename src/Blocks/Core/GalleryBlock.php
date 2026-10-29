<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

class GalleryBlock extends PageBlock
{
    public static function name(): string
    {
        return 'gallery';
    }

    public static function label(): string
    {
        return __('Gallery');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSquares2x2;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            static::itemTooltips(Repeater::make('images'), __('image'))
                ->label(__('Images'))
                ->schema([
                    static::imageUpload('image', __('Image'))->required(),
                    TextInput::make('alt')->label(__('Alternative text'))->required()->maxLength(200)
                        ->hintIcon(self::HELP_ICON, __('A few words describing the image, read out to people who can\'t see it, e.g. "The main hall set up for a wedding".')),
                    TextInput::make('caption')->label(__('Caption'))->maxLength(200),
                ])
                ->minItems(1)
                ->defaultItems(1)
                ->grid(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['alt'] ?? null)
                ->addActionLabel(__('Add image')),
        ];
    }

    protected static function styleToggleTitle(): string
    {
        return __('Image');
    }

    protected static function styleFields(): array
    {
        return [
            static::imageStyleFieldset(),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            static::columnsSelect(3),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        return [
            'imageStyle' => static::imageStyle($data),
            'images' => collect($data['images'] ?? [])
                ->map(fn (mixed $image): ?array => is_array($image) && ($url = static::imageUrl($image['image'] ?? null))
                    ? ['url' => $url, 'alt' => (string) ($image['alt'] ?? ''), 'caption' => $image['caption'] ?? null]
                    : null)
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
