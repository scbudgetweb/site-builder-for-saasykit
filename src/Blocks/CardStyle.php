<?php

namespace ScBudgetWeb\SiteBuilder\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use ScBudgetWeb\SiteBuilder\Blocks\Design\Items;

/**
 * The "Cards" settings for blocks made of cards with an icon, title and text (Features, Image cards): whether each card
 * is a box (a background and a border) and where the icon sits. Saved under the block's "card" key; every value is
 * re-checked in resolve(), so only whitelisted classes reach the page.
 *
 * The cards' colours, border line, shadow, icon and text styles are Design › Items (Design\Items) since 1.7. Blocks saved
 * before then have theirs moved there when they're read (upgrade(), BlockSchema version 3).
 */
class CardStyle
{
    public const ICON_POSITIONS = [
        'top' => 'Above, left-aligned',
        'top_center' => 'Above, centred (centres the text too)',
        'left' => 'Left of the title and text',
    ];

    /** The block types whose cards these are. */
    public const BLOCK_TYPES = ['features', 'image_cards'];

    /** Before 1.7: icon size => the size it is in Design › Items. */
    private const OLD_ICON_SIZES = ['small' => '20px', 'large' => '32px', 'xl' => '40px', 'xxl' => '56px'];

    /** Before 1.7: title and text size => the size it is in Design › Items. */
    private const OLD_TEXT_SIZES = [
        'title' => ['small' => '1rem', 'medium' => '1.125rem', 'large' => '1.25rem', 'xl' => '1.5rem'],
        'text' => ['small' => '0.875rem', 'medium' => '1rem', 'large' => '1.125rem'],
    ];

    /** Before 1.7: card setting => where it is in data.design.items. */
    private const MOVED = [
        'background_color' => 'box.background',
        'border_color' => 'box.border_colour',
        'border_style' => 'box.border_style',
        'border_width' => 'box.border_width',
        'shadow' => 'box.shadow',
        'shadow_x' => 'box.shadow_x',
        'shadow_y' => 'box.shadow_y',
        'shadow_blur' => 'box.shadow_blur',
        'shadow_spread' => 'box.shadow_spread',
        'shadow_color' => 'box.shadow_color',
        'shadow_opacity' => 'box.shadow_opacity',
        'shadow_inset' => 'box.shadow_inset',
        'icon_color' => 'icon.colour',
        'icon_background_color' => 'icon.background',
        'title_color' => 'title.colour',
        'text_color' => 'text.colour',
    ];

    /**
     * For the block's Design › Card layout toggle.
     */
    public static function fieldset(): Group
    {
        return Group::make()
            ->statePath('card')
            ->columns(2)
            ->schema([
                self::onByDefaultToggle('background', __('Card background'))
                    ->hintIcon(PageBlock::HELP_ICON, __('A background and border make each card a box. Turn both off for a lighter look. Their colours, shadow and text are under Design › Items.')),
                self::onByDefaultToggle('border', __('Card border')),
                Select::make('icon_position')
                    ->label(__('Icon position'))
                    ->options(array_map(fn (string $label): string => __($label), self::ICON_POSITIONS))
                    ->default('top')
                    ->afterStateHydrated(function (Select $component, ?string $state): void {
                        $component->state($state ?? 'top');
                    })
                    ->selectablePlaceholder(false),
            ]);
    }

    /**
     * Classes for the cards, from the saved settings and the block's Design › Items ($items, data.design.items), which
     * decides the text colour on a card colour of its own and replaces the standard shadow. Unsaved (a block added in
     * code, or one saved before its cards had settings), cards have a background and a border.
     *
     * @param  array<string, mixed>|null  $card
     * @param  array<string, mixed>|null  $items
     * @return array{card: string, hasBox: bool, textScheme: ?string, isRow: bool, isCentered: bool, iconWrap: string, iconSize: string}
     */
    public static function resolve(mixed $card, mixed $items = null): array
    {
        $card = is_array($card) ? $card : [];

        $hasBackground = (bool) ($card['background'] ?? true);
        $hasBorder = (bool) ($card['border'] ?? true);
        $position = array_key_exists($card['icon_position'] ?? '', self::ICON_POSITIONS) ? $card['icon_position'] : 'top';
        $isDark = Items::hasDarkBackground($items);

        // A card with a background gets dark text, or light text on a dark colour of its own (Design › Items);
        // without one, it takes the block's text colour (so it reads on dark sections too).
        $cardText = match (true) {
            ! $hasBackground && $isDark === null => '',
            $isDark === true => 'text-white',
            default => 'text-gray-900',
        };

        return [
            'card' => trim(implode(' ', array_filter([
                'flex',
                $position === 'left' ? 'flex-row items-start gap-4' : 'flex-col',
                $position === 'top_center' ? 'items-center text-center' : '',
                $hasBackground || $hasBorder ? 'rounded-sb-card p-6' : '',
                $hasBackground ? 'bg-white' : '',
                $hasBorder ? 'border border-gray-200' : '',
                // The site's card shadow, unless Design › Items chooses another.
                $hasBackground && ! Items::hasOwnShadow($items) ? 'shadow-sb-card' : '',
                $cardText,
            ]))),
            // Whether the card is a box (background or border), e.g. so an image can run edge to edge inside it.
            'hasBox' => $hasBackground || $hasBorder,
            // A card with its own background has its own text scheme; otherwise it takes the block's.
            'textScheme' => $cardText === '' ? null : ($cardText === 'text-white' ? 'light' : 'dark'),
            'isRow' => $position === 'left',
            'isCentered' => $position === 'top_center',
            // The standard icon tile; Design › Items changes it (items.css).
            'iconWrap' => trim(implode(' ', array_filter([
                'sb-item-icon sb-has-tile inline-flex shrink-0 items-center justify-center h-12 w-12 rounded-xl bg-primary-50 text-primary-600',
                // A column stretches its children to full width, which would centre the icon in its wrapper.
                $position === 'top_center' ? 'self-center' : 'self-start',
                $position === 'left' ? '' : 'mb-4',
            ]))),
            'iconSize' => 'h-6 w-6',
        ];
    }

    /**
     * A Features or Image cards block's data with its cards' look as saved before 1.7 moved to Design › Items
     * (data.design.items), where nothing is set there already. Blocks already moved are left as they are.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function upgrade(array $data): array
    {
        $card = $data['card'] ?? null;

        if (! is_array($card)) {
            return $data;
        }

        $moved = [];
        // Before 1.7 a card's colour and border line only showed with its background or border on.
        $unused = [
            ...((bool) ($card['background'] ?? true) ? [] : ['background_color']),
            ...((bool) ($card['border'] ?? true) ? [] : ['border_color', 'border_style', 'border_width']),
        ];

        foreach (self::MOVED as $old => $new) {
            if (array_key_exists($old, $card)) {
                if ($card[$old] !== null && $card[$old] !== '' && ! in_array($old, $unused, true)) {
                    $moved[$new] = $card[$old];
                }

                unset($card[$old]);
            }
        }

        if (($card['icon_background'] ?? null) === 'none') {
            $moved['icon.fill'] = 'none';
        }

        if (isset(self::OLD_ICON_SIZES[(string) ($card['icon_size'] ?? '')])) {
            $moved['icon.size'] = ['desktop' => self::OLD_ICON_SIZES[$card['icon_size']]];
        }

        foreach (self::OLD_TEXT_SIZES as $part => $sizes) {
            if (isset($sizes[(string) ($card["{$part}_size"] ?? '')])) {
                $moved["{$part}.size"] = ['desktop' => $sizes[$card["{$part}_size"]]];
            }

            unset($card["{$part}_size"]);
        }

        unset($card['icon_background'], $card['icon_size']);
        $data['card'] = $card;

        if ($moved === []) {
            return $data;
        }

        $design = is_array($data['design'] ?? null) ? $data['design'] : [];
        $items = is_array($design['items'] ?? null) ? $design['items'] : [];

        foreach ($moved as $path => $value) {
            if (blank(Arr::get($items, $path))) {
                Arr::set($items, $path, $value);
            }
        }

        $design['items'] = $items;
        $data['design'] = $design;

        return $data;
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
}
