<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\BoxSides;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;

/**
 * Layout › Spacing & size: padding and margin for each side, the content's max width and the block's
 * line-up, and a minimum height with where the content sits in it. Every length can differ by screen size. Saved under
 * data.design.spacing; resources/css/design/spacing.css reads it.
 *
 * Padding goes on the box (the panel, or the section without one) and overrides Space above and below; margin, the
 * minimum height and the content's line-up go on the section; the max width on the container.
 */
final class Spacing implements DesignGroup
{
    public const UNITS = ['px', 'rem', '%'];

    public const MAX = 400;

    /** Side => the short name in its class and property (sb-d-pt, --sb-pt…). */
    private const SIDES = ['top' => 't', 'right' => 'r', 'bottom' => 'b', 'left' => 'l'];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public const VERTICAL_ALIGNMENTS = ['top' => 'Top', 'center' => 'Middle', 'bottom' => 'Bottom'];

    public static function key(): string
    {
        return 'spacing';
    }

    public static function toggles(): array
    {
        $path = 'design.'.self::key();

        return [
            PanelToggle::design('sizing', __('Sizing'), [
                DeviceValue::make('width')
                    ->label(__('Width'))
                    ->hintIcon(PageBlock::HELP_ICON, __('How wide the block\'s content is, e.g. 60% or 480 px. Line it up below. Blank: as wide as it can be.'))
                    ->units(['%', 'px', 'rem', 'vw'])
                    ->range(0, 3000)
                    ->live(onBlur: true),
                DeviceValue::make('max_width')
                    ->label(__('Max width'))
                    ->hintIcon(PageBlock::HELP_ICON, __('The widest the block\'s content gets, in place of its Content width. Line it up below.'))
                    ->units(['px', 'rem', '%'])
                    ->range(0, 3000)
                    ->live(onBlur: true),
                ToggleButtons::make('align')
                    ->label(__('Line up the content'))
                    ->options(array_map(fn (string $label): string => __($label), self::ALIGNMENTS))
                    ->icons(['left' => 'heroicon-m-bars-3-bottom-left', 'center' => 'heroicon-m-bars-3', 'right' => 'heroicon-m-bars-3-bottom-right'])
                    ->inline()
                    ->grouped()
                    ->visible(fn (Get $get): bool => filled($get('max_width')) || filled($get('width'))),
                DeviceValue::make('min_height')
                    ->label(__('Min height'))
                    ->hintIcon(PageBlock::HELP_ICON, __('The shortest the block gets, however little is in it. 100 vh fills the screen.'))
                    ->units(['px', 'vh', 'rem'])
                    ->range(0, 2000)
                    ->live(onBlur: true),
                DeviceValue::make('height')
                    ->label(__('Height'))
                    ->hintIcon(PageBlock::HELP_ICON, __('An exact height. Content that doesn\'t fit spills over, unless Advanced › Visibility hides it.'))
                    ->units(['px', 'vh', 'rem'])
                    ->range(0, 2000),
                DeviceValue::make('max_height')
                    ->label(__('Max height'))
                    ->units(['px', 'vh', 'rem'])
                    ->range(0, 2000),
                ToggleButtons::make('vertical_align')
                    ->label(__('Content sits at the'))
                    ->options(array_map(fn (string $label): string => __($label), self::VERTICAL_ALIGNMENTS))
                    ->inline()
                    ->grouped()
                    ->visible(fn (Get $get): bool => filled($get('min_height'))),
            ], 80, $path),
            PanelToggle::design('spacing', __('Spacing'), [
                BoxSides::make('padding')
                    ->label(__('Space inside (padding)'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Space between the block\'s edge (or its panel\'s) and its content. Replaces Space above and below when set.'))
                    ->units(self::UNITS)
                    ->range(0, self::MAX),
                BoxSides::make('margin')
                    ->label(__('Space outside (margin)'))
                    ->hintIcon(PageBlock::HELP_ICON, __('Space around the block, pushing other blocks away. Below 0 pulls them closer, so blocks can overlap.'))
                    ->units(self::UNITS)
                    ->range(-self::MAX, self::MAX),
            ], 90, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        foreach (self::SIDES as $side => $short) {
            DesignValue::addLength($output, 'box', "p{$short}", self::side($values['padding'] ?? null, $side), self::UNITS, 0, self::MAX);
            DesignValue::addLength($output, 'section', "m{$short}", self::side($values['margin'] ?? null, $side), self::UNITS, -self::MAX, self::MAX);
        }

        DesignValue::addLength($output, 'container', 'w', $values['width'] ?? null, ['%', 'px', 'rem', 'vw'], 0, 3000);
        DesignValue::addLength($output, 'container', 'maxw', $values['max_width'] ?? null, ['px', 'rem', '%'], 0, 3000);
        DesignValue::addLength($output, 'section', 'h', $values['height'] ?? null, ['px', 'vh', 'rem'], 0, 2000);
        DesignValue::addLength($output, 'section', 'maxh', $values['max_height'] ?? null, ['px', 'vh', 'rem'], 0, 2000);
        DesignValue::addLength($output, 'section', 'minh', $values['min_height'] ?? null, ['px', 'vh', 'rem'], 0, 2000);

        if (($align = DesignValue::choice($values['align'] ?? null, self::ALIGNMENTS)) !== null) {
            $output->addClass('container', "sb-d-align-{$align}");
        }

        if (($verticalAlign = DesignValue::choice($values['vertical_align'] ?? null, self::VERTICAL_ALIGNMENTS)) !== null) {
            $output->addClass('section', "sb-d-valign-{$verticalAlign}");
        }

        return $output;
    }

    /**
     * One side's value for each screen size, from BoxSides' {desktop: {top…}, tablet: …, phone: …}.
     *
     * @return array<string, mixed>
     */
    private static function side(mixed $state, string $side): array
    {
        return array_map(
            fn (mixed $sides): mixed => is_array($sides) ? ($sides[$side] ?? null) : null,
            DesignValue::devices($state),
        );
    }
}
