<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Panel;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * One group of settings in a block's panel: a collapsible "toggle" on the Content, Design or Advanced tab
 * (SettingsPanel). Several sources can add to the same toggle (the block, Appearance, a design group), each with its own
 * state path, so where a setting shows never changes where it's saved.
 *
 * Order: toggles with a priority below 10 come first, then those without one in the order they're added,
 * then the rest by priority (Background 80, Admin label 99 on Content; Text 49 … Animation 110 on Design).
 */
final class PanelToggle
{
    public const CONTENT = 'content';

    public const DESIGN = 'design';

    public const ADVANCED = 'advanced';

    /**
     * @param  array<int, Component|Field>  $fields
     */
    public function __construct(
        public readonly string $tab,
        public readonly string $key,
        public readonly string $title,
        public readonly array $fields,
        public readonly ?int $priority = null,
        public readonly ?string $statePath = null,
        public readonly bool $isOpen = false,
    ) {}

    /**
     * @param  array<int, Component|Field>  $fields
     */
    public static function content(string $key, string $title, array $fields, ?int $priority = null, ?string $statePath = null, bool $isOpen = false): self
    {
        return new self(self::CONTENT, $key, $title, $fields, $priority, $statePath, $isOpen);
    }

    /**
     * @param  array<int, Component|Field>  $fields
     */
    public static function design(string $key, string $title, array $fields, ?int $priority = null, ?string $statePath = null): self
    {
        return new self(self::DESIGN, $key, $title, $fields, $priority, $statePath);
    }

    /**
     * @param  array<int, Component|Field>  $fields
     */
    public static function advanced(string $key, string $title, array $fields, ?int $priority = null, ?string $statePath = null): self
    {
        return new self(self::ADVANCED, $key, $title, $fields, $priority, $statePath);
    }
}
