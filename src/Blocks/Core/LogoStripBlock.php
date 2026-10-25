<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Core;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Models\Page;

/**
 * Partner or club logos: a centred row, an endless scrolling loop, or a carousel with arrows.
 */
class LogoStripBlock extends PageBlock
{
    public const DISPLAYS = [
        'row' => 'Static row',
        'loop' => 'Scrolling loop',
        'carousel' => 'Carousel (arrows and swipe)',
    ];

    /** Logo size => [label, image classes, carousel slide width]. */
    public const SIZES = [
        'small' => ['Small', 'h-8 max-w-28', 'w-32'],
        'medium' => ['Medium', 'h-12 max-w-40', 'w-44'],
        'large' => ['Large', 'h-16 max-w-48', 'w-52'],
        'xl' => ['Extra large', 'h-20 max-w-56', 'w-60'],
        'xxl' => ['Huge', 'h-28 max-w-72', 'w-80'],
    ];

    /** Loop speed => [label, seconds per logo]. */
    public const SPEEDS = [
        'slow' => ['Slow', 5],
        'normal' => ['Normal', 3],
        'fast' => ['Fast', 1.5],
    ];

    public static function name(): string
    {
        return 'logo_strip';
    }

    public static function label(): string
    {
        return __('Logo strip');
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedRectangleGroup;
    }

    protected static function fields(): array
    {
        return [
            ...static::headingFields(),
            Repeater::make('logos')
                ->label(__('Logos'))
                ->schema([
                    static::imageUpload('image', __('Logo'))->required(),
                    TextInput::make('name')->label(__('Organisation name'))->required()->maxLength(100),
                    TextInput::make('url')->label(__('Website (optional)'))->url()->maxLength(500),
                ])
                ->minItems(1)
                ->defaultItems(1)
                ->grid(3)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->addActionLabel(__('Add logo')),
        ];
    }

    protected static function styleFields(): array
    {
        return [
            Toggle::make('greyscale')->label(__('Show logos in greyscale until hovered'))->default(true),
        ];
    }

    protected static function layoutFields(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('display')
                    ->label(__('Display'))
                    ->options(array_map(fn (string $label): string => __($label), self::DISPLAYS))
                    ->default('row')
                    ->selectablePlaceholder(false)
                    ->helperText(__('Moving logos stay still for visitors who turn off animations on their device.'))
                    ->live(),
                Select::make('size')
                    ->label(__('Logo size'))
                    ->options(array_map(fn (array $size): string => __($size[0]), self::SIZES))
                    ->default('medium')
                    ->selectablePlaceholder(false),
                Select::make('speed')
                    ->label(__('Speed'))
                    ->options(array_map(fn (array $speed): string => __($speed[0]), self::SPEEDS))
                    ->default('normal')
                    ->selectablePlaceholder(false)
                    ->visible(fn (Get $get): bool => $get('display') === 'loop'),
                Toggle::make('autoplay')
                    ->label(__('Move on by itself'))
                    ->helperText(__('Every few seconds, pausing while the mouse is over it.'))
                    ->default(true)
                    ->inline(false)
                    ->visible(fn (Get $get): bool => $get('display') === 'carousel'),
            ]),
        ];
    }

    public static function viewData(array $data, Page $page): array
    {
        $display = static::pick($data['display'] ?? null, self::DISPLAYS, 'row');
        $size = self::SIZES[static::pick($data['size'] ?? null, self::SIZES, 'medium')];

        return [
            'display' => $display,
            'imageClass' => $size[1],
            'slideWidth' => $size[2],
            'secondsPerLogo' => self::SPEEDS[static::pick($data['speed'] ?? null, self::SPEEDS, 'normal')][1],
            'autoplay' => (bool) ($data['autoplay'] ?? true),
            'logos' => collect($data['logos'] ?? [])
                ->map(fn (mixed $logo): ?array => is_array($logo) && ($url = static::imageUrl($logo['image'] ?? null))
                    ? ['url' => $url, 'name' => (string) ($logo['name'] ?? ''), 'link' => $logo['url'] ?? null]
                    : null)
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
