<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;

/**
 * Design › Small heading: the eyebrow's text style (Typography::textFields()), a pill's colour, and a decoration
 * beside it: a line on both sides, a line before it, or a dot. Saved under data.design.eyebrow; offered on blocks with
 * an eyebrow (PageBlock::hasEyebrow()). resources/css/design/eyebrow.css takes it to .sb-eyebrow.
 */
final class Eyebrow implements DesignGroup
{
    /** The short name in its text classes and properties (sb-d-ebc, --sb-ebc…). */
    public const SHORT = 'eb';

    public const DECORATIONS = [
        'both' => 'A line on both sides',
        'before' => 'A line before it',
        'dot' => 'A dot before it',
    ];

    public static function key(): string
    {
        return 'eyebrow';
    }

    /**
     * None of its own: only blocks with an eyebrow get it (panelToggles()).
     */
    public static function toggles(): array
    {
        return [];
    }

    /**
     * @return array<int, PanelToggle>
     */
    public static function panelToggles(): array
    {
        $path = 'design.'.self::key();
        $isDecorated = fn (Get $get): bool => filled($get('decoration'));

        return [
            PanelToggle::design('eyebrow', __('Small heading'), [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('decoration')
                        ->label(__('Decoration'))
                        ->hintIcon(PageBlock::HELP_ICON, __('A short line or a dot beside the small heading, in its colour unless you choose another.'))
                        ->options(array_map(fn (string $label): string => __($label), self::DECORATIONS))
                        ->placeholder(__('None'))
                        ->live(),
                    SiteColour::make('decoration_colour')
                        ->label(__('Decoration colour'))
                        ->visible($isDecorated),
                    DeviceValue::make('line_length')
                        ->label(__('Line length'))
                        ->units(['px', 'rem'])
                        ->range(4, 200)
                        ->sameOnEveryScreen()
                        ->visible(fn (Get $get): bool => in_array($get('decoration'), ['both', 'before'], true)),
                    SiteColour::make('pill_colour')
                        ->label(__('Pill colour'))
                        ->hintIcon(PageBlock::HELP_ICON, __('The background of a small heading that looks like a pill (Content tab).')),
                ]),
                Typography::textFields(self::key()),
            ], 48, $path),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        Typography::addText($output, $values, self::SHORT);

        if (($decoration = DesignValue::choice($values['decoration'] ?? null, self::DECORATIONS)) !== null) {
            $output->addClass('section', "sb-d-eb-{$decoration}");

            if (($colour = DesignValue::colour($values['decoration_colour'] ?? null)) !== null) {
                $output->setProperty('section', '--sb-ebdc', $colour);
            }

            if (($length = DesignValue::length(DesignValue::devices($values['line_length'] ?? null)['desktop'], ['px', 'rem'], 4, 200)) !== null) {
                $output->setProperty('section', '--sb-ebdw', $length);
            }
        }

        if (($colour = DesignValue::colour($values['pill_colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-ebbg')->setProperty('section', '--sb-ebbg', $colour);
        }

        return $output;
    }
}
