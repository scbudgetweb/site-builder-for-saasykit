<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Border and shadow settings, shared by blocks (their section or panel), Section rows, images, cards and buttons, so
 * they work the same everywhere. Saved next to the thing they style, with an optional key prefix (e.g. "image_"):
 * {prefix}border_style, _width, _color, _sides and {prefix}shadow, _x, _y, _blur, _spread, _color, _opacity.
 *
 * style() turns them into an inline style from whitelisted keywords, clamped numbers and hex colours only, so a
 * hand-edited value can't reach the page (lesson 11).
 */
class BorderShadow
{
    public const BORDER_STYLES = [
        'none' => 'None',
        'solid' => 'Solid',
        'dashed' => 'Dashed',
        'dotted' => 'Dotted',
        'double' => 'Double',
    ];

    /** Sides => [label, CSS sides]. */
    public const SIDES = [
        'all' => ['All sides', ['top', 'right', 'bottom', 'left']],
        'top' => ['Top', ['top']],
        'bottom' => ['Bottom', ['bottom']],
        'top_bottom' => ['Top and bottom', ['top', 'bottom']],
        'left' => ['Left', ['left']],
        'right' => ['Right', ['right']],
        'left_right' => ['Left and right', ['left', 'right']],
    ];

    /** Shadow => [label, CSS box-shadow]; "custom" is built from its own settings. */
    public const SHADOWS = [
        'none' => ['None', 'none'],
        'site' => ['Site style (Branding)', 'var(--shadow-sb-card)'],
        'soft' => ['Soft', '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)'],
        'medium' => ['Medium', '0 4px 12px -2px rgb(0 0 0 / 0.12), 0 2px 4px -2px rgb(0 0 0 / 0.08)'],
        'strong' => ['Strong', '0 10px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1)'],
        'custom' => ['Custom', null],
    ];

    /** Offered where the thing has a natural default (a panel or card): the default is passed to style(). */
    public const AUTOMATIC = 'auto';

    public const MAX_BORDER_WIDTH = 20;

    /** Custom shadow offsets, blur and spread are kept within this many pixels. */
    public const MAX_SHADOW_PIXELS = 100;

    private const HEX = '/^#[0-9a-fA-F]{6}$/';

    /**
     * A "Border & shadow" fieldset for the Style tab.
     */
    public static function fieldset(string $prefix = '', bool $withSides = true, ?string $automaticLabel = null, ?string $label = null): Fieldset
    {
        return Fieldset::make($label ?? __('Border & shadow'))
            ->columns(2)
            ->schema([
                ...self::borderFields($prefix, $withSides),
                ...self::shadowFields($prefix, $automaticLabel),
            ]);
    }

    /**
     * @return array<int, Select|TextInput|ColorPicker>
     */
    public static function borderFields(string $prefix = '', bool $withSides = true): array
    {
        $hasBorder = fn (Get $get): bool => self::pick($get($prefix.'border_style'), self::BORDER_STYLES, 'none') !== 'none';

        return array_values(array_filter([
            Select::make($prefix.'border_style')
                ->label(__('Border'))
                ->hintIcon(PageBlock::HELP_ICON, __('A line around the edge. Choose the line, then its width, colour and which sides.'))
                ->options(array_map(fn (string $label): string => __($label), self::BORDER_STYLES))
                ->default('none')
                ->selectablePlaceholder(false)
                ->live(),
            TextInput::make($prefix.'border_width')
                ->label(__('Border width'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(self::MAX_BORDER_WIDTH)
                ->suffix('px')
                ->default(1)
                ->visible($hasBorder),
            ColorPicker::make($prefix.'border_color')
                ->label(__('Border colour'))
                ->regex(self::HEX)
                ->helperText(__('Blank: a soft line in the text colour.'))
                ->visible($hasBorder),
            $withSides ? Select::make($prefix.'border_sides')
                ->label(__('Sides'))
                ->options(array_map(fn (array $side): string => __($side[0]), self::SIDES))
                ->default('all')
                ->selectablePlaceholder(false)
                ->visible($hasBorder) : null,
        ]));
    }

    /**
     * @param  string|null  $automaticLabel  offers "Automatic" first (and as the default), e.g. "Automatic (site style on a panel)"
     * @return array<int, Select|Grid>
     */
    public static function shadowFields(string $prefix = '', ?string $automaticLabel = null): array
    {
        $isCustom = fn (Get $get): bool => $get($prefix.'shadow') === 'custom';
        $pixels = fn (string $name, string $label, int $default, int $min = -self::MAX_SHADOW_PIXELS): TextInput => TextInput::make($prefix.$name)
            ->label($label)
            ->numeric()
            ->integer()
            ->minValue($min)
            ->maxValue(self::MAX_SHADOW_PIXELS)
            ->suffix('px')
            ->default($default);

        return [
            Select::make($prefix.'shadow')
                ->label(__('Shadow'))
                ->hintIcon(PageBlock::HELP_ICON, __('A soft shadow that lifts it off the page. Custom lets you set its size, softness and colour.'))
                ->options(self::shadowOptions($automaticLabel))
                ->default($automaticLabel === null ? 'none' : self::AUTOMATIC)
                ->selectablePlaceholder(false)
                ->live(),
            Grid::make(['default' => 2, 'md' => 4])
                ->columnSpanFull()
                ->visible($isCustom)
                ->schema([
                    $pixels('shadow_x', __('Across'), 0)->hintIcon(PageBlock::HELP_ICON, __('How far the shadow falls to the right. Below 0: to the left.')),
                    $pixels('shadow_y', __('Down'), 8)->hintIcon(PageBlock::HELP_ICON, __('How far the shadow falls downwards. Below 0: upwards.')),
                    $pixels('shadow_blur', __('Blur'), 24, 0)->hintIcon(PageBlock::HELP_ICON, __('How soft the shadow\'s edge is. 0 is a sharp edge.')),
                    $pixels('shadow_spread', __('Spread'), 0)->hintIcon(PageBlock::HELP_ICON, __('How much bigger (or, below 0, smaller) the shadow is than the thing it\'s under.')),
                    ColorPicker::make($prefix.'shadow_color')
                        ->label(__('Shadow colour'))
                        ->regex(self::HEX)
                        ->placeholder('#000000')
                        ->columnSpan(2),
                    Slider::make($prefix.'shadow_opacity')
                        ->label(__('Shadow strength (%)'))
                        ->range(minValue: 0, maxValue: 100)
                        ->step(5)
                        ->decimalPlaces(0)
                        ->tooltips()
                        ->default(20)
                        ->columnSpan(2),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function shadowOptions(?string $automaticLabel = null, bool $withCustom = true): array
    {
        $options = array_map(fn (array $shadow): string => __($shadow[0]), self::SHADOWS);

        if (! $withCustom) {
            unset($options['custom']);
        }

        return $automaticLabel === null ? $options : [self::AUTOMATIC => $automaticLabel] + $options;
    }

    /**
     * The inline style for the saved settings (border and box-shadow), or '' for none.
     *
     * @param  array<string, mixed>  $values
     * @param  string|null  $defaultShadow  used when no shadow (or Automatic) is saved, e.g. "site" for a panel
     */
    public static function style(array $values, string $prefix = '', ?string $defaultShadow = null): string
    {
        return trim(self::borderStyle($values, $prefix).' '.self::shadowStyle($values, $prefix, $defaultShadow));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function borderStyle(array $values, string $prefix = ''): string
    {
        $style = self::pick($values[$prefix.'border_style'] ?? null, self::BORDER_STYLES, 'none');

        if ($style === 'none') {
            return '';
        }

        $width = self::clamp($values[$prefix.'border_width'] ?? null, 1, self::MAX_BORDER_WIDTH, 1);
        $color = Colour::isValidHex($values[$prefix.'border_color'] ?? null)
            ? $values[$prefix.'border_color']
            : 'color-mix(in oklab, currentColor 20%, transparent)';
        $sides = self::SIDES[self::pick($values[$prefix.'border_sides'] ?? null, self::SIDES, 'all')][1];

        if (count($sides) === 4) {
            return "border: {$width}px {$style} {$color};";
        }

        return implode(' ', array_map(fn (string $side): string => "border-{$side}: {$width}px {$style} {$color};", $sides));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function shadowStyle(array $values, string $prefix = '', ?string $defaultShadow = null): string
    {
        $saved = $values[$prefix.'shadow'] ?? null;
        $shadow = self::pick($saved === self::AUTOMATIC ? null : $saved, self::SHADOWS, $defaultShadow ?? 'none');

        // Shadows are only ever drawn by this inline style, so "none" needs nothing.
        if ($shadow === 'none') {
            return '';
        }

        if ($shadow !== 'custom') {
            return 'box-shadow: '.self::SHADOWS[$shadow][1].';';
        }

        $max = self::MAX_SHADOW_PIXELS;
        $color = Colour::isValidHex($values[$prefix.'shadow_color'] ?? null) ? $values[$prefix.'shadow_color'] : '#000000';
        [$red, $green, $blue] = sscanf($color, '#%02x%02x%02x');
        $opacity = self::clamp($values[$prefix.'shadow_opacity'] ?? null, 0, 100, 20) / 100;

        return sprintf(
            'box-shadow: %dpx %dpx %dpx %dpx rgba(%d, %d, %d, %s);',
            self::clamp($values[$prefix.'shadow_x'] ?? null, -$max, $max, 0),
            self::clamp($values[$prefix.'shadow_y'] ?? null, -$max, $max, 8),
            self::clamp($values[$prefix.'shadow_blur'] ?? null, 0, $max, 24),
            self::clamp($values[$prefix.'shadow_spread'] ?? null, -$max, $max, 0),
            $red, $green, $blue,
            rtrim(rtrim(number_format($opacity, 2, '.', ''), '0'), '.') ?: '0',
        );
    }

    /**
     * Short tags for the editor's block card, e.g. ["Border", "Shadow"].
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    public static function summary(array $values, string $prefix = ''): array
    {
        return array_values(array_filter([
            self::pick($values[$prefix.'border_style'] ?? null, self::BORDER_STYLES, 'none') !== 'none' ? __('Border') : null,
            ! in_array(self::pick($values[$prefix.'shadow'] ?? null, self::SHADOWS, 'none'), ['none', 'site'], true) ? __('Shadow') : null,
        ]));
    }

    private static function clamp(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? max($min, min($max, (int) round((float) $value))) : $default;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function pick(mixed $value, array $options, string $default): string
    {
        return (is_string($value) || is_int($value)) && array_key_exists((string) $value, $options) ? (string) $value : $default;
    }
}
