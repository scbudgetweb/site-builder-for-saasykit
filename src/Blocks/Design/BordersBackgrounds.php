<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\BoxSides;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;

/**
 * Style › Borders & corners, Hover, and Pattern & shape. Saved under data.design.borders; all on the box
 * (the panel, or the section without one). resources/css/design/borders-backgrounds.css reads it.
 *
 *  - Border width per side and a border colour, and a radius per corner. With a border chosen under More options
 *    › Border, its line and colour stay and the widths here replace its width; without one, these draw a solid line.
 *  - Hover colours for the background, text and border, with a short fade.
 *  - A pattern (dots, grid, lines…) drawn in CSS over the background, and a decorative shape (a corner blob, an arc…),
 *    each in a colour and strength, behind the content.
 *
 * The background image's size, position, repeat and "stay still" are with the background itself (BlockAppearance).
 */
final class BordersBackgrounds implements DesignGroup
{
    /** Border width per side and corner radius: side => the short name in its class and property. */
    private const SIDES = ['top' => 't', 'right' => 'r', 'bottom' => 'b', 'left' => 'l'];

    private const CORNERS = ['top_left' => 'tl', 'top_right' => 'tr', 'bottom_right' => 'br', 'bottom_left' => 'bl'];

    public const MAX_BORDER_WIDTH = 20;

    public const MAX_RADIUS = 500;

    public const RADIUS_UNITS = ['px', 'rem', '%'];

    public const PATTERNS = [
        'dots' => 'Dots',
        'grid' => 'Grid',
        'lines' => 'Diagonal lines',
        'stripes' => 'Stripes',
        'checks' => 'Checks',
        'waves' => 'Waves',
    ];

    public const SHAPES = [
        'blob' => 'Blob in the corner',
        'arc' => 'Arc along the bottom',
        'slant' => 'Slant along the bottom',
        'circle' => 'Circle on the right',
        'triangle' => 'Triangle in the corner',
    ];

    public static function key(): string
    {
        return 'borders';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();

        return [
            PanelToggle::design('border', __('Border'), [Grid::make(['default' => 1, 'sm' => 2])->schema([
                BoxSides::make('border_width')
                    ->label(__('Border width'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Line width for each side; 0 hides that side. With a Border line chosen above, its line and colour are kept and these widths replace its width.'))
                    ->units(['px'])
                    ->range(0, self::MAX_BORDER_WIDTH)
                    ->sameOnEveryScreen(),
                HoverMode::normal(SiteColour::make('border_color')
                    ->label(__('Border colour'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Used when no Border line is chosen above. Blank: a soft line in the text colour.'))),
                HoverMode::only(SiteColour::make('hover_border')
                    ->label(__('Border colour on hover'))),
                Select::make('border_line')
                    ->label(__('Line for these widths'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Used when no Border line is chosen above. Blank: solid.'))
                    ->options(array_map(fn (string $label): string => __($label), array_diff_key(BorderShadow::BORDER_STYLES, ['none' => true])))
                    ->placeholder(__('Solid')),
                ...array_map(
                    fn (string $side): SiteColour => SiteColour::make("border_color_{$side}")
                        ->label(__(':side colour', ['side' => __(ucfirst($side))]))
                        ->hintIcon(PageBlock::HELP_ICON, __('This side only. Blank: the border colour.')),
                    array_keys(self::SIDES),
                ),
                BoxSides::make('radius')
                    ->corners()
                    ->label(__('Rounded corners'))
                    ->hintIcon(PageBlock::HELP_ICON, __('How round each corner is. Replaces the Corners setting when set; 50% on all four makes a circle or oval.'))
                    ->units(self::RADIUS_UNITS)
                    ->range(0, self::MAX_RADIUS)
                    ->sameOnEveryScreen(),
            ])], 95, $path),
            // Hover colours sit with the settings they change, shown in the panel's Hover state (HoverMode).
            PanelToggle::content('background', __('Background'), [
                HoverMode::only(SiteColour::make('hover_background')
                    ->label(__('Background on hover'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Replaces the background, including a gradient, while the pointer is over the block.'))),
            ], 80, $path),
            PanelToggle::design('text', __('Text'), [
                HoverMode::only(SiteColour::make('hover_text')
                    ->label(__('Text colour on hover'))),
            ], 49, $path),
            PanelToggle::content('background', __('Background'), [Grid::make(['default' => 1, 'sm' => 2])->schema([
                Select::make('pattern')
                    ->label(__('Pattern'))
                    ->options(array_map(fn (string $label): string => __($label), self::PATTERNS))
                    ->placeholder(__('None'))
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => filled($state) && blank($get('pattern_strength')) ? $set('pattern_strength', 15) : null),
                SiteColour::make('pattern_color')
                    ->label(__('Pattern colour'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Blank: the text colour.'))
                    ->visible(fn (Get $get): bool => filled($get('pattern'))),
                Slider::make('pattern_strength')
                    ->label(__('Pattern strength (%)'))
                    ->range(minValue: 0, maxValue: 100)
                    ->step(5)
                    ->decimalPlaces(0)
                    ->tooltips()
                    ->default(15)
                    ->visible(fn (Get $get): bool => filled($get('pattern'))),
                DeviceValue::make('pattern_size')
                    ->label(__('Pattern size'))
                    ->hintIcon(PageBlock::HELP_ICON, __('How far apart the dots, lines or squares are.'))
                    ->units(['px'])
                    ->range(4, 200)
                    ->sameOnEveryScreen()
                    ->visible(fn (Get $get): bool => filled($get('pattern'))),
                Select::make('shape')
                    ->label(__('Shape'))
                    ->options(array_map(fn (string $label): string => __($label), self::SHAPES))
                    ->placeholder(__('None'))
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => filled($state) && blank($get('shape_strength')) ? $set('shape_strength', 20) : null),
                SiteColour::make('shape_color')
                    ->label(__('Shape colour'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Blank: the text colour.'))
                    ->visible(fn (Get $get): bool => filled($get('shape'))),
                Slider::make('shape_strength')
                    ->label(__('Shape strength (%)'))
                    ->range(minValue: 0, maxValue: 100)
                    ->step(5)
                    ->decimalPlaces(0)
                    ->tooltips()
                    ->default(20)
                    ->visible(fn (Get $get): bool => filled($get('shape'))),
            ])], 80, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        $hasBorder = false;

        foreach (self::SIDES as $side => $short) {
            $width = DesignValue::length(self::part($values['border_width'] ?? null, $side), ['px'], 0, self::MAX_BORDER_WIDTH);

            if ($width !== null) {
                $hasBorder = true;
                $output->addClass('box', "sb-d-bw{$short}")->setProperty('box', "--sb-bw{$short}", $width);
            }

            // While the block is pointed at (hover.css).
            if (($hoverWidth = DesignValue::length(self::part($values['border_width'] ?? null, $side, 'hover'), ['px'], 0, self::MAX_BORDER_WIDTH)) !== null) {
                $hasBorder = true;
                $output->addClass('box', "sb-d-bw{$short}-h")->setProperty('box', "--sb-bw{$short}-h", $hoverWidth);
            }
        }

        if ($hasBorder) {
            $output->addClass('box', 'sb-d-bw');

            if (($color = DesignValue::colour($values['border_color'] ?? null)) !== null) {
                $output->setProperty('box', '--sb-bc', $color);
            }

            if (($line = DesignValue::choice($values['border_line'] ?? null, array_diff_key(BorderShadow::BORDER_STYLES, ['none' => true]))) !== null) {
                $output->setProperty('box', '--sb-bs', $line);
            }

            foreach (self::SIDES as $side => $short) {
                if (($color = DesignValue::colour($values["border_color_{$side}"] ?? null)) !== null) {
                    $output->addClass('box', "sb-d-bc{$short}")->setProperty('box', "--sb-bc{$short}", $color);
                }
            }
        }

        foreach (self::CORNERS as $corner => $short) {
            if (($hoverRadius = DesignValue::length(self::part($values['radius'] ?? null, $corner, 'hover'), self::RADIUS_UNITS, 0, self::MAX_RADIUS)) !== null) {
                $output->addClass('box', "sb-d-r{$short}-h")->setProperty('box', "--sb-r{$short}-h", $hoverRadius);
            }

            $radius = DesignValue::length(self::part($values['radius'] ?? null, $corner), self::RADIUS_UNITS, 0, self::MAX_RADIUS);

            if ($radius !== null) {
                $output->addClass('box', "sb-d-r{$short}")->setProperty('box', "--sb-r{$short}", $radius);
            }
        }

        $hovers = ['hover_background' => 'hbg', 'hover_text' => 'htx', 'hover_border' => 'hbc'];

        foreach ($hovers as $key => $short) {
            if (($color = DesignValue::colour($values[$key] ?? null)) !== null) {
                $output->addClass('box', 'sb-d-hover')->addClass('box', "sb-d-{$short}")->setProperty('box', "--sb-{$short}", $color);
            }
        }

        if (($pattern = DesignValue::choice($values['pattern'] ?? null, self::PATTERNS)) !== null) {
            $output->addClass('box', 'sb-d-layers')->addClass('box', "sb-d-pattern-{$pattern}")
                ->setProperty('box', '--sb-pattern-opacity', self::strength($values['pattern_strength'] ?? null, 15));

            if (($color = DesignValue::colour($values['pattern_color'] ?? null)) !== null) {
                $output->setProperty('box', '--sb-pattern-color', $color);
            }

            if (($size = DesignValue::length(DesignValue::devices($values['pattern_size'] ?? null)['desktop'], ['px'], 4, 200)) !== null) {
                $output->setProperty('box', '--sb-pattern-size', $size);
            }
        }

        if (($shape = DesignValue::choice($values['shape'] ?? null, self::SHAPES)) !== null) {
            $output->addClass('box', 'sb-d-layers')->addClass('box', "sb-d-shape-{$shape}")
                ->setProperty('box', '--sb-shape-opacity', self::strength($values['shape_strength'] ?? null, 20));

            if (($color = DesignValue::colour($values['shape_color'] ?? null)) !== null) {
                $output->setProperty('box', '--sb-shape-color', $color);
            }
        }

        return $output;
    }

    /**
     * One side's (or corner's) value from BoxSides' state; these settings are the same on every screen.
     */
    private static function part(mixed $state, string $part, string $device = 'desktop'): mixed
    {
        $parts = DesignValue::devices($state)[$device];

        return is_array($parts) ? ($parts[$part] ?? null) : null;
    }

    /**
     * A strength from 5 to 100 (%) as an opacity, e.g. "0.15"; anything else (none yet, or 0) is the default.
     */
    private static function strength(mixed $value, int $default): string
    {
        $percent = DesignValue::integer($value, 5, 100) ?? $default;

        return rtrim(rtrim(number_format($percent / 100, 2, '.', ''), '0'), '.');
    }
}
