<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use InvalidArgumentException;

/**
 * The parts of a block's repeated items (cards, numbers, steps, people…), declared once by the block
 * (PageBlock::itemParts()). From it the block's panel gets Design › Items (Items::togglesFor()): the item's box, its icon
 * or marker, and a text style for each text part.
 *
 * Every part has a fixed class, part of the package's public API, so Branding › Custom CSS can rely on it:
 *
 *     .sb-items         the list of items
 *     .sb-item          one item
 *     .sb-item-icon     its icon, or a step's marker
 *     .sb-item-{part}   a text part: title, text, value, label or meta (a role, a date…)
 *
 * The block's view puts them on its elements; BlockFeaturesTest checks every declared part is there.
 */
final class ItemParts
{
    /** Text part => the short name in its classes and properties (sb-d-itt…, --sb-itt…). */
    public const TEXTS = ['title' => 'itt', 'text' => 'itx', 'value' => 'iva', 'label' => 'ilb', 'meta' => 'imt'];

    /**
     * @param  array<string, string>  $texts  text part => its name in the panel
     */
    private function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly array $texts = [],
    ) {}

    /**
     * $label names the items in the panel, e.g. "Cards" or "Numbers".
     */
    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * The items have an icon (or a marker, such as a step's number), named $label in the panel.
     */
    public function icon(string $label): self
    {
        return new self($this->label, $label, $this->texts);
    }

    /**
     * A text part (one of TEXTS), named $label in the panel, e.g. text('value', 'Number').
     */
    public function text(string $part, string $label): self
    {
        if (! isset(self::TEXTS[$part])) {
            throw new InvalidArgumentException("[{$part}] isn't an item text part. Use one of: ".implode(', ', array_keys(self::TEXTS)).'.');
        }

        return new self($this->label, $this->icon, [...$this->texts, $part => $label]);
    }

    /**
     * The classes the block's view must have: sb-items, sb-item, sb-item-icon (with an icon) and sb-item-{part}.
     *
     * @return array<int, string>
     */
    public function classes(): array
    {
        return [
            'sb-items',
            'sb-item',
            ...($this->icon !== null ? ['sb-item-icon'] : []),
            ...array_map(fn (string $part): string => "sb-item-{$part}", array_keys($this->texts)),
        ];
    }
}
