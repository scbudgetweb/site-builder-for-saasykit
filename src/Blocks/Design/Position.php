<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;

/**
 * Transform (Design), Transitions, Position and overflow (Advanced), on the block's section.
 *
 * Scale, move and rotate use CSS's own scale, translate and rotate properties, so they work alongside an entrance
 * animation (which animates transform); only skew needs transform itself. Position offers relative and absolute (to
 * the column or section around it), never fixed: a block can't pin itself over the page (sticky comes with its limits).
 */
class Position implements DesignGroup
{
    /** Where an absolutely placed block sits => [label, CSS for top/right/bottom/left from its offsets]. */
    public const LOCATIONS = [
        'top-left' => 'Top left',
        'top' => 'Top centre',
        'top-right' => 'Top right',
        'left' => 'Middle left',
        'center' => 'Centre',
        'right' => 'Middle right',
        'bottom-left' => 'Bottom left',
        'bottom' => 'Bottom centre',
        'bottom-right' => 'Bottom right',
    ];

    public const POSITIONS = [
        'relative' => 'Relative (moved by the offsets, keeping its space)',
        'absolute' => 'Absolute (placed in the column or section around it)',
    ];

    public const ORIGINS = [
        'top-left' => 'Top left',
        'top' => 'Top',
        'top-right' => 'Top right',
        'left' => 'Left',
        'center' => 'Centre',
        'right' => 'Right',
        'bottom-left' => 'Bottom left',
        'bottom' => 'Bottom',
        'bottom-right' => 'Bottom right',
    ];

    public const CURVES = [
        'ease' => 'Ease',
        'ease-in' => 'Ease in',
        'ease-out' => 'Ease out',
        'ease-in-out' => 'Ease in and out',
        'linear' => 'Linear',
    ];

    public const OVERFLOWS = [
        'visible' => 'Visible',
        'hidden' => 'Hidden',
        'auto' => 'Scroll when needed',
        'scroll' => 'Always scroll',
    ];

    /** Number setting => [label, unit, min, max]. Blank changes nothing. */
    private const TRANSFORMS = [
        'scale_x' => ['Scale across (%)', '%', -100, 300],
        'scale_y' => ['Scale down (%)', '%', -100, 300],
        'rotate' => ['Rotate (degrees)', 'deg', -360, 360],
        'skew_x' => ['Skew across (degrees)', 'deg', -180, 180],
        'skew_y' => ['Skew down (degrees)', 'deg', -180, 180],
    ];

    private const MOVE_UNITS = ['px', 'rem', '%'];

    public static function key(): string
    {
        return 'position';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();
        $number = function (string $name, array $setting): TextInput {
            $field = TextInput::make($name)
                ->label(__($setting[0]))
                ->numeric()
                ->minValue($setting[2])
                ->maxValue($setting[3]);

            // Hidden in the Hover state, where Motion's hover scale and rotate show (skew has no hover value: dimmed).
            return in_array($name, ['scale_x', 'scale_y', 'rotate'], true) ? HoverMode::normal($field) : $field;
        };
        $isPlaced = fn (Get $get): bool => filled($get('position'));

        return [
            PanelToggle::design('transform', __('Transform'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    ...array_map($number, array_keys(self::TRANSFORMS), self::TRANSFORMS),
                    DeviceValue::make('translate_x')
                        ->label(__('Move across'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Moves the block without moving anything around it. Below 0 moves it left.'))
                        ->units(self::MOVE_UNITS)
                        ->range(-1000, 1000),
                    DeviceValue::make('translate_y')
                        ->label(__('Move down'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Below 0 moves it up.'))
                        ->units(self::MOVE_UNITS)
                        ->range(-1000, 1000),
                    Select::make('origin')
                        ->label(__('Transform from the'))
                        ->hintIcon(PageBlock::HELP_ICON, __('The point it scales, rotates and skews around.'))
                        ->options(self::translated(self::ORIGINS))
                        ->placeholder(__('Centre')),
                ]),
            ], 109, $path),
            PanelToggle::advanced('visibility', __('Visibility'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('overflow_x')
                        ->label(__('Content wider than the block'))
                        ->options(self::translated(self::OVERFLOWS))
                        ->placeholder(__('Default')),
                    Select::make('overflow_y')
                        ->label(__('Content taller than the block'))
                        ->options(self::translated(self::OVERFLOWS))
                        ->placeholder(__('Default')),
                ]),
            ], 99, $path),
            PanelToggle::advanced('transitions', __('Transitions'), [
                Grid::make(['default' => 1, 'sm' => 3])->schema([
                    TextInput::make('transition_duration')
                        ->label(__('Duration (ms)'))
                        ->hintIcon(PageBlock::HELP_ICON, __('How long hover changes (colours, transform) take. Blank: 300 ms.'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(2000),
                    TextInput::make('transition_delay')
                        ->label(__('Delay (ms)'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(3000),
                    Select::make('transition_curve')
                        ->label(__('Speed curve'))
                        ->options(self::translated(self::CURVES))
                        ->placeholder(__('Ease')),
                ]),
            ], 120, $path),
            PanelToggle::advanced('position', __('Position'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('position')
                        ->label(__('Position'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Relative nudges the block from where it would be. Absolute takes it out of the flow and places it in the column or section around it, e.g. a badge in a corner.'))
                        ->options(self::translated(self::POSITIONS))
                        ->placeholder(__('Default'))
                        ->live(),
                    Select::make('location')
                        ->label(__('Location'))
                        ->options(self::translated(self::LOCATIONS))
                        ->default('top-left')
                        ->selectablePlaceholder(false)
                        ->visible(fn (Get $get): bool => $get('position') === 'absolute'),
                    DeviceValue::make('offset_x')
                        ->label(__('Across'))
                        ->units(self::MOVE_UNITS)
                        ->range(-1000, 1000)
                        ->visible($isPlaced),
                    DeviceValue::make('offset_y')
                        ->label(__('Down'))
                        ->units(self::MOVE_UNITS)
                        ->range(-1000, 1000)
                        ->visible($isPlaced),
                    TextInput::make('z_index')
                        ->label(__('Layer (z-index)'))
                        ->hintIcon(PageBlock::HELP_ICON, __('Which block is on top where blocks overlap: higher is nearer the front.'))
                        ->numeric()
                        ->integer()
                        ->minValue(-500)
                        ->maxValue(500),
                ]),
            ], 190, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;
        $number = fn (string $name): ?float => is_numeric($values[$name] ?? null)
            && (float) $values[$name] >= self::TRANSFORMS[$name][2] && (float) $values[$name] <= self::TRANSFORMS[$name][3]
                ? (float) $values[$name] : null;
        $tidy = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        // Transform: scale and rotate as their own properties; skew through transform.
        [$scaleX, $scaleY] = [$number('scale_x'), $number('scale_y')];

        if ($scaleX !== null || $scaleY !== null) {
            $output->addClass('section', 'sb-d-scale')
                ->setProperty('section', '--sb-scale', $tidy(($scaleX ?? 100) / 100).' '.$tidy(($scaleY ?? 100) / 100));
        }

        if (($rotate = $number('rotate')) !== null) {
            $output->addClass('section', 'sb-d-rotate')->setProperty('section', '--sb-rotate', $tidy($rotate).'deg');
        }

        [$skewX, $skewY] = [$number('skew_x'), $number('skew_y')];

        if ($skewX !== null || $skewY !== null) {
            $output->addClass('section', 'sb-d-skew')
                ->setProperty('section', '--sb-skew', 'skew('.$tidy($skewX ?? 0).'deg, '.$tidy($skewY ?? 0).'deg)');
        }

        DesignValue::addLength($output, 'section', 'tx', $values['translate_x'] ?? null, self::MOVE_UNITS, -1000, 1000);
        DesignValue::addLength($output, 'section', 'ty', $values['translate_y'] ?? null, self::MOVE_UNITS, -1000, 1000);

        if (($origin = DesignValue::choice($values['origin'] ?? null, self::ORIGINS)) !== null) {
            $output->addClass('section', "sb-d-origin-{$origin}");
        }

        // Overflow.
        foreach (['overflow_x' => 'ovx', 'overflow_y' => 'ovy'] as $name => $short) {
            if (($overflow = DesignValue::choice($values[$name] ?? null, self::OVERFLOWS)) !== null) {
                $output->addClass('section', "sb-d-{$short}-{$overflow}");
            }
        }

        // Transitions.
        if (($duration = DesignValue::integer($values['transition_duration'] ?? null, 0, 2000)) !== null) {
            $output->addClass('section', 'sb-d-transition')->setProperty('section', '--sb-transition-duration', "{$duration}ms");
        }

        if (($delay = DesignValue::integer($values['transition_delay'] ?? null, 0, 3000)) !== null) {
            $output->addClass('section', 'sb-d-transition')->setProperty('section', '--sb-transition-delay', "{$delay}ms");
        }

        if (($curve = DesignValue::choice($values['transition_curve'] ?? null, self::CURVES)) !== null) {
            $output->addClass('section', 'sb-d-transition')->setProperty('section', '--sb-transition-curve', $curve);
        }

        // Position and layer.
        if (($position = DesignValue::choice($values['position'] ?? null, self::POSITIONS)) !== null) {
            $output->addClass('section', "sb-d-pos-{$position}");

            if ($position === 'absolute') {
                $output->addClass('section', 'sb-d-at-'.(DesignValue::choice($values['location'] ?? null, self::LOCATIONS) ?? 'top-left'));
            }

            DesignValue::addLength($output, 'section', 'ox', $values['offset_x'] ?? null, self::MOVE_UNITS, -1000, 1000);
            DesignValue::addLength($output, 'section', 'oy', $values['offset_y'] ?? null, self::MOVE_UNITS, -1000, 1000);
        }

        if (($zIndex = DesignValue::integer($values['z_index'] ?? null, -500, 500)) !== null) {
            $output->addClass('section', 'sb-d-z')->setProperty('section', '--sb-z', (string) $zIndex);
        }

        return $output;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function translated(array $options): array
    {
        return array_map(fn (string $label): string => __($label), $options);
    }
}
