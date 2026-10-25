<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\BlockAppearance;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * A full-width page banner. Uses the Branding hero or inner-page banner by default, or its own image.
 * As the page's first block it holds the page's h1, and the standard page banner is left out.
 */
class BannerBlock extends PageBlock
{
    /** Block types that are full-width banners: a page opening with one skips the standard page banner. */
    public const TYPES = ['banner', 'hero'];

    /** Heading size => [label, classes]. */
    public const HEADING_SIZES = [
        'small' => ['Small', 'text-3xl sm:text-4xl'],
        'standard' => ['Standard', 'text-4xl sm:text-5xl lg:text-6xl'],
        'large' => ['Large', 'text-4xl sm:text-6xl lg:text-7xl'],
        'xl' => ['Extra large', 'text-5xl sm:text-7xl lg:text-8xl'],
    ];

    /** Subtitle size => [label, classes]. */
    public const SUBTITLE_SIZES = [
        'small' => ['Small', 'text-base sm:text-lg'],
        'standard' => ['Standard', 'text-lg sm:text-xl'],
        'large' => ['Large', 'text-xl sm:text-2xl'],
    ];

    public const STYLES = [
        'hero' => 'Same as the home page banner (Branding › Banners › Hero)',
        'inner' => 'Same as inner page banners (Branding › Banners › Inner pages)',
        'image' => 'Custom image',
    ];

    /** Height => [label, classes]. */
    public const HEIGHTS = [
        'compact' => ['Compact', 'py-10 sm:py-14'],
        'standard' => ['Standard', 'py-16 sm:py-24'],
        'tall' => ['Tall', 'py-24 sm:py-36'],
        'screen' => ['Full screen', 'min-h-[calc(100svh-4rem)] flex flex-col justify-center py-16'],
    ];

    public static function name(): string
    {
        return 'banner';
    }

    public static function label(): string
    {
        return __('Banner');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedPresentationChartBar;
    }

    public static function hasAppearance(): bool
    {
        return false;
    }

    protected static function defaultHeight(): string
    {
        return 'standard';
    }

    protected static function defaultHeadingSize(): string
    {
        return 'standard';
    }

    protected static function defaultSubtitleSize(): string
    {
        return 'standard';
    }

    public static function view(): string
    {
        return 'site-builder::blocks.banner';
    }

    protected static function fields(): array
    {
        return [
            TextInput::make('eyebrow')
                ->label(__('Small text above the heading (optional)'))
                ->helperText(__('A short label, e.g. "New" or "For community groups".'))
                ->maxLength(60),
            TextInput::make('heading')->label(__('Heading'))->required()->maxLength(150)->live(onBlur: true),
            Textarea::make('subtitle')->label(__('Subtitle'))->rows(2)->maxLength(300),
            static::buttonsRepeater(2),
        ];
    }

    protected static function styleFields(): array
    {
        $isImage = fn (Get $get): bool => $get('style') === 'image';
        $sizeOptions = fn (array $sizes): array => array_map(fn (array $size): string => __($size[0]), $sizes);

        return [
            Fieldset::make(__('Background'))
                ->columns(2)
                ->schema([
                    Select::make('style')
                        ->label(__('Background'))
                        ->options(array_map(fn (string $label): string => __($label), self::STYLES))
                        ->default('hero')
                        ->selectablePlaceholder(false)
                        ->live(),
                    Select::make('image_position')
                        ->label(__('Keep in view when cropped'))
                        ->options(array_map(fn (string $label): string => __($label), Colour::IMAGE_POSITIONS))
                        ->default('center')
                        ->selectablePlaceholder(false)
                        ->visible($isImage),
                    static::imageUpload('image', __('Background image'))
                        ->helperText(__('JPG, PNG or WebP, up to 2 MB. A wide image around 1920 × 800 pixels works best.'))
                        ->required($isImage)
                        ->visible($isImage)
                        ->columnSpanFull(),
                    ColorPicker::make('overlay_color')
                        ->label(__('Overlay colour'))
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->placeholder(Colour::DEFAULT_OVERLAY_COLOR)
                        ->visible($isImage),
                    Slider::make('overlay_opacity')
                        ->label(__('Overlay opacity (%)'))
                        ->helperText(__('Text is easier to read on a busy image with an overlay of 40% or more.'))
                        ->range(minValue: 0, maxValue: 100)
                        ->step(5)
                        ->decimalPlaces(0)
                        ->tooltips()
                        ->default(Colour::DEFAULT_OVERLAY_OPACITY)
                        ->visible($isImage),
                ]),
            Fieldset::make(__('Text'))
                ->columns(2)
                ->schema([
                    Select::make('heading_size')
                        ->label(__('Heading size'))
                        ->options($sizeOptions(self::HEADING_SIZES))
                        ->default(static::defaultHeadingSize())
                        ->selectablePlaceholder(false),
                    Select::make('subtitle_size')
                        ->label(__('Subtitle size'))
                        ->options($sizeOptions(self::SUBTITLE_SIZES))
                        ->default(static::defaultSubtitleSize())
                        ->selectablePlaceholder(false),
                ]),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('height')
                    ->label(__('Height'))
                    ->options(array_map(fn (array $height): string => __($height[0]), self::HEIGHTS))
                    ->default(static::defaultHeight())
                    ->selectablePlaceholder(false),
                Select::make('align')
                    ->label(__('Text alignment'))
                    ->options(['center' => __('Centre'), 'left' => __('Left')])
                    ->default('center')
                    ->selectablePlaceholder(false),
            ]),
        ];
    }

    protected static function advancedFields(): array
    {
        return [
            BlockAppearance::cssClassField(),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $style = static::pick($data['style'] ?? null, self::STYLES, 'hero');
        $imageUrl = $style === 'image' ? static::imageUrl($data['image'] ?? null) : null;

        return [
            'variant' => $style === 'inner' ? 'inner' : 'hero',
            'imageUrl' => $imageUrl,
            'imageOverlay' => $imageUrl ? [
                'color' => Colour::isValidHex($data['overlay_color'] ?? null) ? $data['overlay_color'] : Colour::DEFAULT_OVERLAY_COLOR,
                'opacity' => Colour::overlayOpacity($data['overlay_opacity'] ?? null) / 100.0,
            ] : null,
            'imagePosition' => static::pick($data['image_position'] ?? null, Colour::IMAGE_POSITIONS, 'center'),
            'headingClass' => self::HEADING_SIZES[static::pick($data['heading_size'] ?? null, self::HEADING_SIZES, static::defaultHeadingSize())][1],
            'subtitleClass' => self::SUBTITLE_SIZES[static::pick($data['subtitle_size'] ?? null, self::SUBTITLE_SIZES, static::defaultSubtitleSize())][1],
            'heightClass' => self::HEIGHTS[static::pick($data['height'] ?? null, self::HEIGHTS, static::defaultHeight())][1],
            'cssClass' => BlockAppearance::cssClasses($data['css_class'] ?? null),
            'isLeftAligned' => ($data['align'] ?? 'center') === 'left',
            'buttons' => static::validButtons($data['buttons'] ?? null),
            'blockName' => static::name(),
        ];
    }
}
