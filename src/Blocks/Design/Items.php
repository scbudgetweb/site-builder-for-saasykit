<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use ScBudgetWeb\SiteBuilder\Blocks\BorderShadow;
use ScBudgetWeb\SiteBuilder\Blocks\PageBlock;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\HoverMode;
use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;
use ScBudgetWeb\SiteBuilder\Filament\Forms\DeviceValue;
use ScBudgetWeb\SiteBuilder\Filament\Forms\SiteColour;
use ScBudgetWeb\SiteBuilder\Support\Colour;

/**
 * Design › Items: the look of a block's repeated items (cards, numbers, steps, people…), for blocks that declare their
 * parts (ItemParts, PageBlock::itemParts()). Saved under data.design.items as:
 *
 *  - box: the item's background (colour or gradient), padding, corners, border, an accent edge along one side, shadow,
 *    lines between items, the space between them, and how it changes while pointed at;
 *  - icon: the icon's (or marker's) shape, fill, colours and size;
 *  - title, text, value, label, meta: each text part's font, size, colour… (Typography::textFields()).
 *
 * Like every design group it adds classes and properties to the block's section, and resources/css/design/items.css
 * takes them to .sb-items, .sb-item and its parts. An item's own look (a card's white background, a step's circle)
 * stays in its view, and a setting here replaces it only when it's set.
 */
final class Items implements DesignGroup
{
    public const SHAPES = ['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle'];

    /** Icon fill => label. Every one but "none" draws a tile behind the icon. */
    public const FILLS = [
        'none' => 'None (just the icon)',
        'soft' => 'Soft (a light tint)',
        'solid' => 'Solid (the background colour)',
        'outline' => 'Outline (a ring)',
    ];

    public const ACCENTS = ['top' => 'Top', 'left' => 'Left', 'bottom' => 'Bottom', 'right' => 'Right'];

    public const GRADIENTS = ['down' => 'Top to bottom', 'right' => 'Left to right', 'diagonal' => 'Diagonal'];

    private const GRADIENT_ANGLES = ['down' => '180deg', 'right' => '90deg', 'diagonal' => '135deg'];

    public const LIFTS = ['small' => ['Small', '-2px'], 'medium' => ['Medium', '-4px'], 'large' => ['Large', '-8px']];

    public const BORDER_STYLES = ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];

    public static function key(): string
    {
        return 'items';
    }

    /**
     * None of its own: a block's parts decide them (togglesFor()).
     */
    public static function toggles(): array
    {
        return [];
    }

    /**
     * The block's Design toggles for its items: their box, their icon (if they have one) and each text part.
     *
     * @return array<int, PanelToggle>
     */
    public static function togglesFor(ItemParts $parts): array
    {
        $path = 'design.'.self::key();

        return [
            PanelToggle::design('items', $parts->label, [self::boxFields()], 45, "{$path}.box"),
            ...($parts->icon !== null ? [PanelToggle::design('items_icon', $parts->icon, [self::iconFields()], 46, "{$path}.icon")] : []),
            ...array_map(
                fn (string $part, string $label): PanelToggle => PanelToggle::design("items_{$part}", $label, [Typography::textFields($part)], 47, "{$path}.{$part}"),
                array_keys($parts->texts),
                $parts->texts,
            ),
        ];
    }

    private static function boxFields(): Grid
    {
        $hasDividers = fn (Get $get): bool => (bool) $get('dividers');

        return Grid::make(['default' => 1, 'sm' => 2])->schema([
            HoverMode::normal(SiteColour::make('background')
                ->label(__('Background'))
                ->hintIcon(PageBlock::HELP_ICON, __('Each item\'s background. The text turns light on a dark colour. Blank: the standard look.'))),
            HoverMode::only(SiteColour::make('background_hover')
                ->label(__('Background on hover'))),
            SiteColour::make('background_to')
                ->label(__('Gradient to'))
                ->hintIcon(PageBlock::HELP_ICON, __('A second colour, for a gradient from the background colour. Blank: a plain colour.')),
            Select::make('gradient')
                ->label(__('Gradient direction'))
                ->options(self::translated(self::GRADIENTS))
                ->placeholder(__('Top to bottom')),
            DeviceValue::make('padding')
                ->label(__('Padding'))
                ->hintIcon(PageBlock::HELP_ICON, __('The space inside each item, around its content.'))
                ->units(['px', 'rem'])
                ->range(0, 200),
            DeviceValue::make('gap')
                ->label(__('Space between'))
                ->hintIcon(PageBlock::HELP_ICON, __('The space between one item and the next.'))
                ->units(['px', 'rem'])
                ->range(0, 200),
            DeviceValue::make('radius')
                ->label(__('Corners'))
                ->hintIcon(PageBlock::HELP_ICON, __('How rounded each item\'s corners are. 0 is square.'))
                ->units(['px'])
                ->range(0, 200)
                ->sameOnEveryScreen(),
            TextInput::make('border_width')
                ->label(__('Border width'))
                ->hintIcon(PageBlock::HELP_ICON, __('A line around each item. 0 takes the line away.'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(BorderShadow::MAX_BORDER_WIDTH)
                ->suffix('px'),
            Select::make('border_style')
                ->label(__('Border line'))
                ->options(self::translated(self::BORDER_STYLES))
                ->placeholder(__('Solid')),
            HoverMode::normal(SiteColour::make('border_colour')
                ->label(__('Border colour'))),
            HoverMode::only(SiteColour::make('border_colour_hover')
                ->label(__('Border colour on hover'))),
            Select::make('accent')
                ->label(__('Accent edge'))
                ->hintIcon(PageBlock::HELP_ICON, __('A thicker line of colour along one side of each item.'))
                ->options(self::translated(self::ACCENTS))
                ->placeholder(__('None'))
                ->live(),
            TextInput::make('accent_width')
                ->label(__('Accent width'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(BorderShadow::MAX_BORDER_WIDTH)
                ->suffix('px')
                ->placeholder('4')
                ->visible(fn (Get $get): bool => filled($get('accent'))),
            SiteColour::make('accent_colour')
                ->label(__('Accent colour'))
                ->helperText(__('Blank: the site colour.'))
                ->visible(fn (Get $get): bool => filled($get('accent'))),
            ...array_map(
                fn (mixed $field): mixed => $field instanceof Select ? HoverMode::normal($field) : $field,
                BorderShadow::shadowFields('', __('Standard')),
            ),
            HoverMode::only(Select::make('shadow_hover')
                ->label(__('Shadow on hover'))
                ->options(BorderShadow::shadowOptions(withCustom: false))
                ->placeholder(__('No change'))),
            HoverMode::only(Select::make('lift')
                ->label(__('Lift on hover'))
                ->hintIcon(PageBlock::HELP_ICON, __('The item rises a little while it\'s pointed at.'))
                ->options(array_map(fn (array $lift): string => __($lift[0]), self::LIFTS))
                ->placeholder(__('None'))),
            Toggle::make('dividers')
                ->label(__('Lines between items'))
                ->hintIcon(PageBlock::HELP_ICON, __('Thin lines between the items instead of gaps, like a grid. Takes the place of the shadow.'))
                ->live(),
            SiteColour::make('divider_colour')
                ->label(__('Line colour'))
                ->helperText(__('Blank: a faint line in the text colour.'))
                ->visible($hasDividers),
        ]);
    }

    private static function iconFields(): Grid
    {
        return Grid::make(['default' => 1, 'sm' => 2])->schema([
            Select::make('fill')
                ->label(__('Background'))
                ->hintIcon(PageBlock::HELP_ICON, __('A tile behind the icon: a light tint, solid colour or ring. Blank: the standard look.'))
                ->options(self::translated(self::FILLS))
                ->placeholder(__('Standard')),
            Select::make('shape')
                ->label(__('Shape'))
                ->options(self::translated(self::SHAPES))
                ->placeholder(__('Standard')),
            DeviceValue::make('size')
                ->label(__('Size'))
                ->hintIcon(PageBlock::HELP_ICON, __('The icon\'s size. A tile grows with it.'))
                ->units(['px', 'rem'])
                ->range(8, 200),
            HoverMode::normal(SiteColour::make('colour')
                ->label(__('Colour'))
                ->hintIcon(PageBlock::HELP_ICON, __('The icon\'s (or number\'s) colour.'))),
            HoverMode::only(SiteColour::make('colour_hover')
                ->label(__('Colour on hover'))),
            SiteColour::make('background')
                ->label(__('Tile colour'))
                ->hintIcon(PageBlock::HELP_ICON, __('The tile\'s colour, or the ring\'s with Outline. Blank: the site colour.')),
        ]);
    }

    public static function resolve(array $values, bool $hasPanel): DesignOutput
    {
        $output = new DesignOutput;

        self::resolveBox($output, is_array($values['box'] ?? null) ? $values['box'] : []);
        self::resolveIcon($output, is_array($values['icon'] ?? null) ? $values['icon'] : []);

        foreach (ItemParts::TEXTS as $part => $short) {
            Typography::addText($output, $values[$part] ?? null, $short);
        }

        return $output;
    }

    /**
     * Whether text on the items' background should be light: true on a dark colour, false on a light one, null when
     * they have no colour of their own.
     *
     * @param  array<string, mixed>|null  $items  data.design.items
     */
    public static function hasDarkBackground(mixed $items): ?bool
    {
        $background = is_array($items) && is_array($items['box'] ?? null) ? ($items['box']['background'] ?? null) : null;

        if (DesignValue::colour($background) === null) {
            return null;
        }

        return ! Colour::isReadableTextOn('#111827', (string) $background);
    }

    /**
     * Whether the items have a shadow of their own (so a block's standard card shadow gives way).
     *
     * @param  array<string, mixed>|null  $items  data.design.items
     */
    public static function hasOwnShadow(mixed $items): bool
    {
        $box = is_array($items) && is_array($items['box'] ?? null) ? $items['box'] : [];

        return isset(BorderShadow::SHADOWS[(string) ($box['shadow'] ?? '')]) || (bool) ($box['dividers'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $box
     */
    private static function resolveBox(DesignOutput $output, array $box): void
    {
        $background = DesignValue::colour($box['background'] ?? null);
        $backgroundTo = DesignValue::colour($box['background_to'] ?? null);

        if ($background !== null) {
            $angle = self::GRADIENT_ANGLES[DesignValue::choice($box['gradient'] ?? null, self::GRADIENTS) ?? 'down'];

            $output->addClass('section', 'sb-d-ibg')
                ->setProperty('section', '--sb-ibg', $backgroundTo !== null ? "linear-gradient({$angle}, {$background}, {$backgroundTo})" : $background)
                // Text that reads on it, as a text scheme does (items.css).
                ->addClass('section', self::hasDarkBackground(['box' => $box]) ? 'sb-d-ibg-light' : 'sb-d-ibg-dark');
        }

        if (($colour = DesignValue::colour($box['background_hover'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-ibg-h')->setProperty('section', '--sb-ibg-h', $colour);
        }

        DesignValue::addLength($output, 'section', 'ip', $box['padding'] ?? null, ['px', 'rem'], 0, 200);
        DesignValue::addLength($output, 'section', 'igap', $box['gap'] ?? null, ['px', 'rem'], 0, 200);
        DesignValue::addLength($output, 'section', 'ir', $box['radius'] ?? null, ['px'], 0, 200);

        if (($width = DesignValue::integer($box['border_width'] ?? null, 0, BorderShadow::MAX_BORDER_WIDTH)) !== null) {
            $output->addClass('section', 'sb-d-ibw')->setProperty('section', '--sb-ibw', "{$width}px");
        }

        if (($style = DesignValue::choice($box['border_style'] ?? null, self::BORDER_STYLES)) !== null) {
            $output->addClass('section', 'sb-d-ibs')->setProperty('section', '--sb-ibs', $style);
        }

        if (($colour = DesignValue::colour($box['border_colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-ibc')->setProperty('section', '--sb-ibc', $colour);
        }

        if (($colour = DesignValue::colour($box['border_colour_hover'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-ibc-h')->setProperty('section', '--sb-ibc-h', $colour);
        }

        if (($accent = DesignValue::choice($box['accent'] ?? null, self::ACCENTS)) !== null) {
            $output->addClass('section', "sb-d-iacc-{$accent}");

            if (($width = DesignValue::integer($box['accent_width'] ?? null, 1, BorderShadow::MAX_BORDER_WIDTH)) !== null) {
                $output->setProperty('section', '--sb-iaw', "{$width}px");
            }

            if (($colour = DesignValue::colour($box['accent_colour'] ?? null)) !== null) {
                $output->setProperty('section', '--sb-iac', $colour);
            }
        }

        if (isset(BorderShadow::SHADOWS[(string) ($box['shadow'] ?? '')])) {
            $shadow = trim(substr(BorderShadow::shadowStyle($box), strlen('box-shadow:')), ' ;');
            $output->addClass('section', 'sb-d-ish')->setProperty('section', '--sb-ish', $shadow !== '' ? $shadow : 'none');
        }

        if (($shadow = DesignValue::choice($box['shadow_hover'] ?? null, BorderShadow::shadowOptions(withCustom: false))) !== null) {
            $output->addClass('section', 'sb-d-ish-h')->setProperty('section', '--sb-ish-h', (string) BorderShadow::SHADOWS[$shadow][1]);
        }

        if (($lift = DesignValue::choice($box['lift'] ?? null, self::LIFTS)) !== null) {
            $output->addClass('section', 'sb-d-ilift')->setProperty('section', '--sb-ilift', self::LIFTS[$lift][1]);
        }

        if ((bool) ($box['dividers'] ?? false)) {
            $output->addClass('section', 'sb-d-idiv');

            if (($colour = DesignValue::colour($box['divider_colour'] ?? null)) !== null) {
                $output->setProperty('section', '--sb-idc', $colour);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $icon
     */
    private static function resolveIcon(DesignOutput $output, array $icon): void
    {
        if (($fill = DesignValue::choice($icon['fill'] ?? null, self::FILLS)) !== null) {
            $output->addClass('section', "sb-d-ic-{$fill}");
        }

        if (($shape = DesignValue::choice($icon['shape'] ?? null, self::SHAPES)) !== null) {
            $output->addClass('section', "sb-d-ic-{$shape}");
        }

        DesignValue::addLength($output, 'section', 'ics', $icon['size'] ?? null, ['px', 'rem'], 8, 200);

        if (($colour = DesignValue::colour($icon['colour'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-icc')->setProperty('section', '--sb-icc', $colour);
        }

        if (($colour = DesignValue::colour($icon['colour_hover'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-icc-h')->setProperty('section', '--sb-icc-h', $colour);
        }

        if (($colour = DesignValue::colour($icon['background'] ?? null)) !== null) {
            $output->addClass('section', 'sb-d-icb')->setProperty('section', '--sb-icb', $colour);
        }
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
