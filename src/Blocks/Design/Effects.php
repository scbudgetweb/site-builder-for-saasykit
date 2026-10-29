<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;

/**
 * Advanced › Effects: hiding the block on phones, tablets or desktops, an entrance animation as it scrolls
 * into view, and filters on its images (with full colour on hover). Saved under data.design.effects;
 * resources/css/design/effects.css reads it. Everything goes on the section.
 *
 * Animations are CSS only (scroll-driven), so there's no script and nothing stays hidden where a browser can't run
 * them, and visitors who prefer reduced motion get none. On the visual builder's canvas a hidden block shows faded,
 * and nothing animates.
 */
final class Effects implements DesignGroup
{
    public const SCREENS = ['phone' => 'Phones', 'tablet' => 'Tablets', 'desktop' => 'Desktops'];

    public const ANIMATIONS = [
        'fade' => 'Fade in',
        'slide-up' => 'Slide up',
        'slide-left' => 'Slide in from the left',
        'slide-right' => 'Slide in from the right',
        'zoom' => 'Zoom in',
        'slide-down' => 'Slide down',
        'bounce' => 'Bounce in',
        'flip' => 'Flip in',
        'fold' => 'Fold in',
        'roll' => 'Roll in',
    ];

    public const SPEEDS = ['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'];

    /** Speed => its duration in ms, when no exact duration is set. */
    private const SPEED_DURATIONS = ['slow' => 1200, 'normal' => 800, 'fast' => 450];

    public const CURVES = [
        'ease-out' => 'Ease out',
        'ease' => 'Ease',
        'ease-in' => 'Ease in',
        'ease-in-out' => 'Ease in and out',
        'linear' => 'Linear',
    ];

    /** How the whole block mixes with what's behind it (CSS mix-blend-mode). */
    public const BLEND_MODES = [
        'multiply' => 'Multiply',
        'screen' => 'Screen',
        'overlay' => 'Overlay',
        'darken' => 'Darken',
        'lighten' => 'Lighten',
        'color-dodge' => 'Colour dodge',
        'color-burn' => 'Colour burn',
        'hard-light' => 'Hard light',
        'soft-light' => 'Soft light',
        'difference' => 'Difference',
        'exclusion' => 'Exclusion',
        'hue' => 'Hue',
        'saturation' => 'Saturation',
        'color' => 'Colour',
        'luminosity' => 'Luminosity',
    ];

    /** Filter => [label, CSS function, unit, min, max, the value that changes nothing]. */
    public const FILTERS = [
        'greyscale' => ['Greyscale (%)', 'grayscale', '%', 0, 100, 0],
        'brightness' => ['Brightness (%)', 'brightness', '%', 0, 200, 100],
        'contrast' => ['Contrast (%)', 'contrast', '%', 0, 200, 100],
        'saturation' => ['Colour strength (%)', 'saturate', '%', 0, 200, 100],
        'blur' => ['Blur (px)', 'blur', 'px', 0, 10, 0],
        'opacity' => ['Opacity (%)', 'opacity', '%', 0, 100, 100],
        'hue' => ['Hue shift (degrees)', 'hue-rotate', 'deg', 0, 359, 0],
        'invert' => ['Invert (%)', 'invert', '%', 0, 100, 0],
        'sepia' => ['Sepia (%)', 'sepia', '%', 0, 100, 0],
    ];

    public static function key(): string
    {
        return 'effects';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();

        return [
            PanelToggle::design('filters', __('Filters'), [
                Text::make(__('Change how the pictures in the block look.')),
                Grid::make(['default' => 1, 'sm' => 2])
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        // Number inputs, not sliders: empty means no change (a slider would save its lowest value). Each has
                        // a hover value, shown in the panel's Hover state.
                        ...array_merge(...array_map(
                            fn (string $name, array $filter): array => [
                                HoverMode::normal(TextInput::make($name)
                                    ->label(__($filter[0]))
                                    ->numeric()
                                    ->integer()
                                    ->minValue($filter[3])
                                    ->maxValue($filter[4])
                                    ->placeholder((string) $filter[5])),
                                HoverMode::only(TextInput::make("{$name}_hover")
                                    ->label(__(':filter on hover', ['filter' => __($filter[0])]))
                                    ->numeric()
                                    ->integer()
                                    ->minValue($filter[3])
                                    ->maxValue($filter[4])
                                    ->placeholder((string) $filter[5])),
                            ],
                            array_keys(self::FILTERS),
                            self::FILTERS,
                        )),
                        Select::make('blend')
                            ->label(__('Blend with what\'s behind'))
                            ->hintIcon(PageBlock::HELP_ICON, __('The whole block, not just its pictures, mixes with the block or background behind it.'))
                            ->options(array_map(fn (string $label): string => __($label), self::BLEND_MODES))
                            ->placeholder(__('Normal'))
                            ->columnSpanFull(),
                        HoverMode::only(Toggle::make('filter_hover')
                            ->label(__('Pictures back to normal on hover'))
                            ->hintIcon(PageBlock::HELP_ICON, __('Pictures go back to normal when someone points at them, e.g. greyscale logos that light up.'))
                            ->columnSpanFull()),
                    ]),
            ], 105, $path),
            PanelToggle::design('animation', __('Animation'), [
                Select::make('animation')
                    ->label(__('Entrance animation'))
                    ->hintIcon(PageBlock::HELP_ICON, __('How the block appears as it scrolls into view. Visitors who ask their device for less motion see it without one.'))
                    ->options(array_map(fn (string $label): string => __($label), self::ANIMATIONS))
                    ->placeholder(__('None'))
                    ->live(),
                ToggleButtons::make('animation_speed')
                    ->label(__('Animation speed'))
                    ->options(array_map(fn (string $label): string => __($label), self::SPEEDS))
                    ->default('normal')
                    ->inline()
                    ->grouped()
                    ->visible(fn (Get $get): bool => filled($get('animation'))),
                Grid::make(['default' => 1, 'sm' => 2])
                    ->visible(fn (Get $get): bool => filled($get('animation')))
                    ->schema([
                        Select::make('animation_target')
                            ->label(__('Animate'))
                            ->hintIcon(PageBlock::HELP_ICON, __('Each item: the block\'s cards, features, testimonials, steps… come in one after another.'))
                            ->options(['block' => __('The whole block'), 'items' => __('Each item, one after another')])
                            ->default('block')
                            ->selectablePlaceholder(false)
                            ->columnSpanFull(),
                        TextInput::make('animation_duration')
                            ->label(__('Duration (ms)'))
                            ->hintIcon(PageBlock::HELP_ICON, __('Replaces the speed when set.'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(5000),
                        TextInput::make('animation_delay')
                            ->label(__('Delay (ms)'))
                            ->hintIcon(PageBlock::HELP_ICON, __('Wait this long after it comes into view, e.g. to bring cards in one after another.'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(5000),
                        TextInput::make('animation_intensity')
                            ->label(__('Intensity (%)'))
                            ->hintIcon(PageBlock::HELP_ICON, __('How far it slides, zooms or turns. Blank: 50%.'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('animation_opacity')
                            ->label(__('Starting opacity (%)'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->placeholder('0'),
                        Select::make('animation_curve')
                            ->label(__('Speed curve'))
                            ->options(array_map(fn (string $label): string => __($label), self::CURVES))
                            ->placeholder(__('Ease out')),
                        Toggle::make('animation_loop')
                            ->label(__('Repeat for ever'))
                            ->inline(false),
                    ]),
            ], 110, $path),
            PanelToggle::advanced('visibility', __('Visibility'), [
                CheckboxList::make('hide_on')
                    ->label(__('Hide on'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Visitors on these screens don\'t see the block at all. Phones are under 768 pixels wide, tablets up to 1024. In the visual builder it still shows, faded, so you can edit it.'))
                    ->options(array_map(fn (string $label): string => __($label), self::SCREENS))
                    ->columns(3),
            ], 99, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        foreach (is_array($values['hide_on'] ?? null) ? $values['hide_on'] : [] as $screen) {
            if (DesignValue::choice($screen, self::SCREENS) !== null) {
                $output->addClass('section', "sb-d-hide-{$screen}");
            }
        }

        if (($animation = DesignValue::choice($values['animation'] ?? null, self::ANIMATIONS)) !== null) {
            $output->addClass('section', 'sb-d-animate')->addClass('section', "sb-d-animate-{$animation}");

            $speed = DesignValue::choice($values['animation_speed'] ?? null, self::SPEEDS) ?? 'normal';
            $duration = DesignValue::integer($values['animation_duration'] ?? null, 0, 5000) ?? self::SPEED_DURATIONS[$speed];
            $output->setProperty('section', '--sb-anim-duration', "{$duration}ms");

            if (($delay = DesignValue::integer($values['animation_delay'] ?? null, 0, 5000)) !== null) {
                $output->setProperty('section', '--sb-anim-delay', "{$delay}ms");
            }

            if (($intensity = DesignValue::integer($values['animation_intensity'] ?? null, 0, 100)) !== null) {
                $output->setProperty('section', '--sb-anim-k', rtrim(rtrim(number_format($intensity / 50, 2, '.', ''), '0'), '.') ?: '0');
            }

            if (($opacity = DesignValue::integer($values['animation_opacity'] ?? null, 0, 100)) !== null) {
                $output->setProperty('section', '--sb-anim-opacity', rtrim(rtrim(number_format($opacity / 100, 2, '.', ''), '0'), '.') ?: '0');
            }

            if (($curve = DesignValue::choice($values['animation_curve'] ?? null, self::CURVES)) !== null) {
                $output->setProperty('section', '--sb-anim-curve', $curve);
            }

            if (($values['animation_target'] ?? null) === 'items') {
                $output->addClass('section', 'sb-d-animate-items');
            }

            if (($values['animation_loop'] ?? false) === true) {
                $output->addClass('section', 'sb-d-animate-loop');
            }
        }

        if (($blend = DesignValue::choice($values['blend'] ?? null, self::BLEND_MODES)) !== null) {
            $output->addClass('section', "sb-d-blend-{$blend}");
        }

        $hoverFilter = self::filter($values, '_hover');

        if ($hoverFilter !== '') {
            $output->addClass('section', 'sb-d-filter-h')->setProperty('section', '--sb-filter-h', $hoverFilter);
        }

        $filter = self::filter($values);

        if ($filter !== '') {
            $output->addClass('section', 'sb-d-filter')->setProperty('section', '--sb-filter', $filter);

            if (($values['filter_hover'] ?? false) === true) {
                $output->addClass('section', 'sb-d-filter-hover');
            }
        }

        return $output;
    }

    /**
     * The images' CSS filter from the values that change something, e.g. "grayscale(100%) brightness(110%)".
     *
     * @param  array<string, mixed>  $values
     */
    private static function filter(array $values, string $suffix = ''): string
    {
        $parts = [];

        foreach (self::FILTERS as $name => [, $function, $unit, $min, $max, $neutral]) {
            $value = DesignValue::integer($values[$name.$suffix] ?? null, $min, $max);

            if ($value !== null && $value !== $neutral) {
                $parts[] = "{$function}({$value}{$unit})";
            }
        }

        return implode(' ', $parts);
    }
}
