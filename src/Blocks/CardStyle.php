<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * The "Card style" settings for blocks made of cards with an icon, title and text (e.g. Features):
 * card background and border (on, off or a colour), where the icon sits, and the icon, title and text
 * colours. Saved under the block's "card" key; every value is re-checked in resolve(), so only whitelisted
 * classes and validated hex colours reach the page. Blank colours keep the standard look.
 */
class CardStyle
{
    public const ICON_POSITIONS = [
        'top' => 'Above, left-aligned',
        'top_center' => 'Above, centred (centres the text too)',
        'left' => 'Left of the title and text',
    ];

    public const ICON_BACKGROUNDS = [
        'tile' => 'Tile (a light square behind the icon)',
        'none' => 'None (just the icon)',
    ];

    /** A card border's line: the usual solid line, or one of these. */
    public const BORDER_STYLES = [
        'solid' => 'Solid',
        'dashed' => 'Dashed',
        'dotted' => 'Dotted',
        'double' => 'Double',
    ];

    /** Icon size => [label, icon classes, tile classes]. "medium" is the standard look. */
    public const ICON_SIZES = [
        'small' => ['Small', 'h-5 w-5', 'h-10 w-10'],
        'medium' => ['Medium', 'h-6 w-6', 'h-12 w-12'],
        'large' => ['Large', 'h-8 w-8', 'h-14 w-14'],
        'xl' => ['Extra large', 'h-10 w-10', 'h-16 w-16'],
        'xxl' => ['Huge', 'h-14 w-14', 'h-20 w-20'],
    ];

    /** Title size => [label, CSS font size]; "standard" keeps the card's own size. */
    public const TITLE_SIZES = [
        'standard' => ['Standard', null],
        'small' => ['Small', '1rem'],
        'medium' => ['Medium', '1.125rem'],
        'large' => ['Large', '1.25rem'],
        'xl' => ['Extra large', '1.5rem'],
    ];

    /** Text size => [label, CSS font size]; "standard" follows the block's Appearance › Text size. */
    public const TEXT_SIZES = [
        'standard' => ['Standard', null],
        'small' => ['Small', '0.875rem'],
        'medium' => ['Medium', '1rem'],
        'large' => ['Large', '1.125rem'],
    ];

    /**
     * For the block's Style tab. Blank colours keep the standard look.
     */
    public static function fieldset(): Fieldset
    {
        $hasBackground = fn (Get $get): bool => (bool) $get('background');
        $hasBorder = fn (Get $get): bool => (bool) $get('border');
        $hasTile = fn (Get $get): bool => $get('icon_background') !== 'none';

        return Fieldset::make(__('Cards'))
            ->statePath('card')
            ->columns(2)
            ->schema([
                self::onByDefaultToggle('background', __('Card background')),
                self::colorField('background_color', __('Background colour'))
                    ->placeholder('#ffffff')
                    ->visible($hasBackground),
                self::onByDefaultToggle('border', __('Card border')),
                self::colorField('border_color', __('Border colour'))
                    ->placeholder('#e5e7eb')
                    ->visible($hasBorder),
                Select::make('border_style')
                    ->label(__('Border line'))
                    ->options(array_map(fn (string $label): string => __($label), self::BORDER_STYLES))
                    ->default('solid')
                    ->selectablePlaceholder(false)
                    ->visible($hasBorder),
                TextInput::make('border_width')
                    ->label(__('Border width'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(BorderShadow::MAX_BORDER_WIDTH)
                    ->suffix('px')
                    ->default(1)
                    ->visible($hasBorder),
                ...BorderShadow::shadowFields('', __('Automatic (site style on cards with a background)')),
                Select::make('icon_position')
                    ->label(__('Icon position'))
                    ->options(array_map(fn (string $label): string => __($label), self::ICON_POSITIONS))
                    ->default('top')
                    ->afterStateHydrated(function (Select $component, ?string $state): void {
                        $component->state($state ?? 'top');
                    })
                    ->selectablePlaceholder(false),
                Select::make('icon_background')
                    ->label(__('Icon background'))
                    ->options(array_map(fn (string $label): string => __($label), self::ICON_BACKGROUNDS))
                    ->default('tile')
                    ->selectablePlaceholder(false)
                    ->live(),
                self::colorField('icon_color', __('Icon colour'))
                    ->helperText(__('Blank: the site colour.')),
                self::colorField('icon_background_color', __('Icon tile colour'))
                    ->helperText(__('Blank: a light tint of the site colour.'))
                    ->visible($hasTile),
                self::sizeSelect('icon_size', __('Icon size'), array_map(fn (array $size): string => $size[0], self::ICON_SIZES), 'medium'),
                self::sizeSelect('title_size', __('Title size'), array_map(fn (array $size): string => $size[0], self::TITLE_SIZES), 'standard'),
                self::colorField('title_color', __('Title colour')),
                self::sizeSelect('text_size', __('Text size'), array_map(fn (array $size): string => $size[0], self::TEXT_SIZES), 'standard'),
                self::colorField('text_color', __('Text colour')),
            ]);
    }

    /**
     * Classes and inline styles for the cards, from the saved settings. Unsaved (a block added in code, or one saved
     * before its cards had settings), cards have a background and a border.
     *
     * @param  array<string, mixed>|null  $card
     * @return array{card: string, cardStyle: string, hasBox: bool, textScheme: ?string, isRow: bool, isCentered: bool, iconWrap: string, iconStyle: string, iconSize: string, titleStyle: string, textStyle: string}
     */
    public static function resolve(mixed $card): array
    {
        $card = is_array($card) ? $card : [];

        $hasBackground = (bool) ($card['background'] ?? true);
        $hasBorder = (bool) ($card['border'] ?? true);
        $position = array_key_exists($card['icon_position'] ?? '', self::ICON_POSITIONS) ? $card['icon_position'] : 'top';
        $hasTile = ($card['icon_background'] ?? 'tile') !== 'none';

        $color = fn (string $key): ?string => Colour::isValidHex($card[$key] ?? null) ? $card[$key] : null;
        $iconSize = self::ICON_SIZES[self::pick($card['icon_size'] ?? null, self::ICON_SIZES, 'medium')];
        $titleSize = self::TITLE_SIZES[self::pick($card['title_size'] ?? null, self::TITLE_SIZES, 'standard')][1];
        $textSize = self::TEXT_SIZES[self::pick($card['text_size'] ?? null, self::TEXT_SIZES, 'standard')][1];
        $backgroundColor = $hasBackground ? $color('background_color') : null;

        // A card with a background gets dark text, or white text on a dark custom colour; without one, it
        // takes the block's text colour (so it reads on dark sections too).
        $cardText = match (true) {
            ! $hasBackground => '',
            $backgroundColor !== null && ! Colour::isReadableTextOn('#111827', $backgroundColor) => 'text-white',
            default => 'text-gray-900',
        };

        return [
            'card' => trim(implode(' ', array_filter([
                'flex',
                $position === 'left' ? 'flex-row items-start gap-4' : 'flex-col',
                $position === 'top_center' ? 'items-center text-center' : '',
                $hasBackground || $hasBorder ? 'rounded-sb-card p-6' : '',
                $hasBackground && ! $backgroundColor ? 'bg-white' : '',
                $hasBorder ? ($color('border_color') ? 'border' : 'border border-gray-200') : '',
                $cardText,
            ]))),
            'cardStyle' => trim(implode(' ', array_filter([
                $backgroundColor ? "background-color: {$backgroundColor};" : '',
                $hasBorder && $color('border_color') ? "border-color: {$color('border_color')};" : '',
                $hasBorder ? self::borderLineStyle($card) : '',
                // Cards with a background have the site's card shadow unless they choose another.
                BorderShadow::shadowStyle($card, '', $hasBackground ? 'site' : 'none'),
            ]))),
            // Whether the card is a box (background or border), e.g. so an image can run edge to edge inside it.
            'hasBox' => $hasBackground || $hasBorder,
            // A card with its own background has its own text scheme; otherwise it takes the block's.
            'textScheme' => $hasBackground ? ($cardText === 'text-white' ? 'light' : 'dark') : null,
            'isRow' => $position === 'left',
            'isCentered' => $position === 'top_center',
            'iconWrap' => trim(implode(' ', array_filter([
                'inline-flex shrink-0 items-center justify-center',
                // A column stretches its children to full width, which would centre the icon in its wrapper.
                $position === 'top_center' ? 'self-center' : 'self-start',
                $position === 'left' ? '' : 'mb-4',
                $hasTile ? $iconSize[2].' rounded-xl' : '',
                $hasTile && ! $color('icon_background_color') ? 'bg-primary-50' : '',
                $color('icon_color') ? '' : 'text-primary-600',
            ]))),
            'iconStyle' => trim(($hasTile && $color('icon_background_color') ? "background-color: {$color('icon_background_color')}; " : '').($color('icon_color') ? "color: {$color('icon_color')};" : '')),
            'iconSize' => $iconSize[1],
            'titleStyle' => trim(($color('title_color') ? "color: {$color('title_color')}; " : '').($titleSize ? "font-size: {$titleSize};" : '')),
            'textStyle' => trim(($color('text_color') ? "color: {$color('text_color')}; opacity: 1; " : '').($textSize ? "font-size: {$textSize};" : '')),
        ];
    }

    /**
     * The border's line and width, when they differ from the standard 1px solid line.
     *
     * @param  array<string, mixed>  $card
     */
    private static function borderLineStyle(array $card): string
    {
        $style = self::pick($card['border_style'] ?? null, self::BORDER_STYLES, 'solid');
        $width = is_numeric($card['border_width'] ?? null) ? max(1, min(BorderShadow::MAX_BORDER_WIDTH, (int) $card['border_width'])) : 1;

        return trim(($style !== 'solid' ? "border-style: {$style}; " : '').($width !== 1 ? "border-width: {$width}px;" : ''));
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function sizeSelect(string $name, string $label, array $options, string $default): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(array_map(fn (string $option): string => __($option), $options))
            ->default($default)
            ->afterStateHydrated(function (Select $component, ?string $state) use ($default): void {
                $component->state($state ?? $default);
            })
            ->selectablePlaceholder(false);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function pick(mixed $value, array $options, string $default): string
    {
        return (is_string($value) || is_int($value)) && array_key_exists((string) $value, $options) ? (string) $value : $default;
    }

    /**
     * On by default. A Toggle with no saved value hydrates as off (lesson 3), which would remove the card from a
     * block saved before its cards had settings. Card settings are always saved with an icon position, so a
     * missing one means nothing was saved: show the switch on.
     */
    private static function onByDefaultToggle(string $name, string $label): Toggle
    {
        return Toggle::make($name)
            ->label($label)
            ->default(true)
            ->live()
            ->afterStateHydrated(function (Toggle $component, Get $get): void {
                if ($get('icon_position') === null) {
                    $component->state(true);
                }
            });
    }

    private static function colorField(string $name, string $label): ColorPicker
    {
        return ColorPicker::make($name)
            ->label($label)
            ->regex('/^#[0-9a-fA-F]{6}$/')
            ->validationMessages(['regex' => __('Enter a 6-digit hex colour, for example #1d41d5.')]);
    }
}
