<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;

/**
 * Hover values, sticky elements and scroll effects, on the block's section.
 *
 *  - Hover values for Transform (scale, lift, rotate), Filters (opacity) and Box shadow, shown in the panel's Hover
 *    state beside the settings they change.
 *  - Sticky: the block stays at the top or bottom of the window while its section (or the page) scrolls past.
 *  - Scroll effects: move, fade, scale, turn or blur as the block passes through the window, from its value as it comes
 *    in, through normal in the middle, to its value as it leaves. Done by the browser's scroll-driven animations (no
 *    script); where they aren't supported, or for visitors who ask for less motion, the block stays still.
 */
class Motion implements DesignGroup
{
    public const STICKY = [
        'top' => 'Top of the window',
        'bottom' => 'Bottom of the window',
    ];

    /** Scroll effect => [label, unit, min, max, the value that changes nothing]. */
    public const SCROLL_EFFECTS = [
        'move_y' => ['Move up or down (px)', 'px', -400, 400, 0],
        'move_x' => ['Move across (px)', 'px', -400, 400, 0],
        'fade' => ['Opacity (%)', '', 0, 100, 100],
        'scale' => ['Scale (%)', '', 10, 300, 100],
        'rotate' => ['Rotate (degrees)', 'deg', -360, 360, 0],
        'blur' => ['Blur (px)', 'px', 0, 20, 0],
    ];

    /** Hover setting => [label, min, max, the value that changes nothing]. */
    private const HOVERS = [
        'hover_scale' => ['Scale (%)', 50, 200, 100],
        'hover_lift' => ['Lift (px)', -100, 100, 0],
        'hover_rotate' => ['Rotate (degrees)', -360, 360, 0],
        'hover_opacity' => ['Block opacity (%)', 0, 100, 100],
    ];

    public static function key(): string
    {
        return 'motion';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();
        $number = fn (string $name, string $label, float $min, float $max): TextInput => TextInput::make($name)
            ->label(__($label))
            ->numeric()
            ->minValue($min)
            ->maxValue($max);

        return [
            // Hover values sit with the settings they change, shown in the panel's Hover state (HoverMode).
            PanelToggle::design('transform', __('Transform'), [
                HoverMode::only(Select::make('hover_target')
                    ->label(__('Hover changes'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Each item: the card, feature or testimonial being pointed at changes, not the whole block.'))
                    ->options(['block' => __('The whole block'), 'items' => __('Each item')])
                    ->default('block')
                    ->selectablePlaceholder(false)),
                Grid::make(['default' => 1, 'sm' => 2])->schema(array_map(
                    fn (string $name): TextInput => HoverMode::only($number($name, self::HOVERS[$name][0].' on hover', self::HOVERS[$name][1], self::HOVERS[$name][2])),
                    ['hover_scale', 'hover_lift', 'hover_rotate'],
                )),
            ], 109, $path),
            PanelToggle::design('filters', __('Filters'), [
                HoverMode::only($number('hover_opacity', self::HOVERS['hover_opacity'][0].' on hover', self::HOVERS['hover_opacity'][1], self::HOVERS['hover_opacity'][2])),
            ], 105, $path),
            PanelToggle::design('box_shadow', __('Box shadow'), [
                HoverMode::only(Select::make('hover_shadow')
                    ->label(__('Shadow on hover'))
                    ->options(array_map(fn (array $shadow): string => __($shadow[0]), array_diff_key(BorderShadow::SHADOWS, ['custom' => true])))
                    ->placeholder(__('Unchanged'))),
            ], 100, $path),
            PanelToggle::advanced('scroll_effects', __('Scroll effects'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('sticky')
                        ->label(__('Stick to the'))
                        ->hintIcon(PageBlock::HELP_ICON, __('The block stays in view while the rest of its section (or the page, for a block on its own) scrolls past.'))
                        ->options(array_map(fn (string $label): string => __($label), self::STICKY))
                        ->placeholder(__('Doesn\'t stick'))
                        ->live(),
                    TextInput::make('sticky_offset')
                        ->label(__('Gap from the edge (px)'))
                        ->hintIcon(PageBlock::HELP_ICON, __('E.g. the height of a header that stays at the top.'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(400)
                        ->visible(fn (Get $get): bool => filled($get('sticky'))),
                ]),
                Text::make(__('As the block scrolls through the window: its value as it comes in, normal in the middle, and its value as it leaves. Blank: no change.')),
                ...array_map(
                    fn (string $effect, array $setting): Fieldset => Fieldset::make(__($setting[0]))
                        ->columns(2)
                        ->schema([
                            $number("scroll_{$effect}_in", 'Coming in', $setting[2], $setting[3]),
                            $number("scroll_{$effect}_out", 'Leaving', $setting[2], $setting[3]),
                        ]),
                    array_keys(self::SCROLL_EFFECTS),
                    self::SCROLL_EFFECTS,
                ),
            ], 200, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;
        $number = fn (string $name, float $min, float $max): ?float => is_numeric($values[$name] ?? null) && (float) $values[$name] >= $min && (float) $values[$name] <= $max
            ? (float) $values[$name] : null;
        $tidy = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';

        // Hover.
        $hovers = [
            'hover_scale' => fn (float $value): array => ['--sb-h-scale', $tidy($value / 100)],
            'hover_lift' => fn (float $value): array => ['--sb-h-lift', $tidy(-$value).'px'],
            'hover_rotate' => fn (float $value): array => ['--sb-h-rotate', $tidy($value).'deg'],
            'hover_opacity' => fn (float $value): array => ['--sb-h-opacity', $tidy($value / 100)],
        ];

        foreach ($hovers as $name => $property) {
            [, $min, $max] = self::HOVERS[$name];

            if (($value = $number($name, $min, $max)) !== null) {
                [$variable, $css] = $property($value);
                $output->addClass('section', 'sb-d-hov')->addClass('section', 'sb-d-'.str_replace('_', '-', $name))->setProperty('section', $variable, $css);
            }
        }

        $shadow = DesignValue::choice($values['hover_shadow'] ?? null, array_diff_key(BorderShadow::SHADOWS, ['custom' => true]));
        $isItems = ($values['hover_target'] ?? null) === 'items';

        if ($shadow !== null) {
            // Each item: the shadow goes on the item being pointed at (the section passes the value down).
            $target = $isItems ? 'section' : 'box';
            $output->addClass($target, 'sb-d-hov')->addClass($target, 'sb-d-hover-shadow')->setProperty($target, '--sb-h-shadow', (string) BorderShadow::SHADOWS[$shadow][1]);
        }

        if ($isItems && str_contains($output->classes('section'), 'sb-d-hov')) {
            $output->addClass('section', 'sb-d-hover-items');
        }

        // Sticky.
        if (($sticky = DesignValue::choice($values['sticky'] ?? null, self::STICKY)) !== null) {
            $output->addClass('section', "sb-d-sticky-{$sticky}");

            if (($offset = DesignValue::integer($values['sticky_offset'] ?? null, 0, 400)) !== null) {
                $output->setProperty('section', '--sb-sticky-offset', "{$offset}px");
            }
        }

        // Scroll effects: each set value replaces the neutral one at the start ("in") or end ("out").
        $hasScroll = false;

        foreach (self::SCROLL_EFFECTS as $effect => [, $unit, $min, $max, $neutral]) {
            foreach (['in', 'out'] as $end) {
                $value = $number("scroll_{$effect}_{$end}", $min, $max);

                if ($value === null || $value == $neutral) {
                    continue;
                }

                $hasScroll = true;
                $css = in_array($effect, ['fade', 'scale'], true) ? $tidy($value / 100) : $tidy($value).$unit;
                $output->setProperty('section', '--sb-s-'.str_replace('_', '-', $effect).'-'.$end, $css);
            }
        }

        if ($hasScroll) {
            $output->addClass('section', 'sb-d-scroll');
        }

        return $output;
    }
}
