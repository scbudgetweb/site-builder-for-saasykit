<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;

/**
 * Divi's Dividers: a shape along the top or bottom edge of a block (a wave, a slant, a curve…), in a colour, usually
 * the colour of the block next to it, so one flows into the other. Drawn by the package's CSS as a mask over a
 * coloured layer (resources/css/design/dividers.css), from its own shapes: nothing typed reaches a URL.
 */
class Dividers implements DesignGroup
{
    public const SHAPES = [
        'slant' => 'Slant',
        'curve' => 'Curve',
        'wave' => 'Wave',
        'waves' => 'Layered waves',
        'triangle' => 'Triangle',
        'arrow' => 'Arrow',
        'mountains' => 'Mountains',
        'zigzag' => 'Zigzag',
    ];

    public const EDGES = ['top' => 't', 'bottom' => 'b'];

    public static function key(): string
    {
        return 'dividers';
    }

    public static function toggles(): array
    {
        $fields = fn (string $edge): array => [
            Select::make("{$edge}_shape")
                ->label($edge === 'top' ? __('Top divider') : __('Bottom divider'))
                ->options(array_map(fn (string $label): string => __($label), self::SHAPES))
                ->placeholder(__('None'))
                ->live(),
            SiteColour::make("{$edge}_colour")
                ->label(__('Colour'))
                ->hintIcon(PageBlock::HELP_ICON, __('Usually the colour of the block next to it, so they flow into each other. Blank: white.'))
                ->visible(fn (Get $get): bool => filled($get("{$edge}_shape"))),
            TextInput::make("{$edge}_height")
                ->label(__('Height (px)'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(500)
                ->placeholder('100')
                ->visible(fn (Get $get): bool => filled($get("{$edge}_shape"))),
            TextInput::make("{$edge}_repeat")
                ->label(__('Repeat'))
                ->hintIcon(PageBlock::HELP_ICON, __('How many times the shape repeats across the block.'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(20)
                ->placeholder('1')
                ->visible(fn (Get $get): bool => filled($get("{$edge}_shape"))),
            Toggle::make("{$edge}_flip")
                ->label(__('Flip left to right'))
                ->inline(false)
                ->visible(fn (Get $get): bool => filled($get("{$edge}_shape"))),
            Toggle::make("{$edge}_above")
                ->label(__('In front of the content'))
                ->inline(false)
                ->visible(fn (Get $get): bool => filled($get("{$edge}_shape"))),
        ];

        return [
            PanelToggle::design('dividers', __('Dividers'), [
                Text::make(__('A shape along the top or bottom edge, so this block flows into the one next to it.')),
                Grid::make(['default' => 1, 'sm' => 2])->schema([...$fields('top'), ...$fields('bottom')]),
            ], 65, 'design.'.self::key()),
        ];
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        foreach (self::EDGES as $edge => $short) {
            if (($shape = DesignValue::choice($values["{$edge}_shape"] ?? null, self::SHAPES)) === null) {
                continue;
            }

            $output->addClass('section', 'sb-d-div')
                ->addClass('section', "sb-d-div{$short}")
                ->addClass('section', "sb-d-div{$short}-{$shape}");

            if (($colour = DesignValue::colour($values["{$edge}_colour"] ?? null)) !== null) {
                $output->setProperty('section', "--sb-div{$short}-c", $colour);
            }

            if (($height = DesignValue::integer($values["{$edge}_height"] ?? null, 0, 500)) !== null) {
                $output->setProperty('section', "--sb-div{$short}-h", "{$height}px");
            }

            if (($repeat = DesignValue::integer($values["{$edge}_repeat"] ?? null, 1, 20)) !== null && $repeat > 1) {
                $output->setProperty('section', "--sb-div{$short}-r", (string) $repeat);
            }

            if (($values["{$edge}_flip"] ?? false) === true) {
                $output->addClass('section', "sb-d-div{$short}-flip");
            }

            if (($values["{$edge}_above"] ?? false) === true) {
                $output->addClass('section', "sb-d-div{$short}-above");
            }
        }

        return $output;
    }
}
