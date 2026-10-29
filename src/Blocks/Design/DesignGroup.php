<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Design;

use ScBudgetWeb\SiteBuilder\Blocks\Panel\PanelToggle;

/**
 * One group of a block's design options (BlockDesign), saved under data.design.{key()}.
 */
interface DesignGroup
{
    /**
     * Where its values are saved: data.design.{key}.
     */
    public static function key(): string;

    /**
     * Its settings, as toggles in the block's panel (SettingsPanel), saved under data.design.{key}.
     *
     * @return array<int, PanelToggle>
     */
    public static function toggles(): array;

    /**
     * Its saved values as classes and CSS properties, every value checked again. $hasPanel: the block's background is
     * a panel (the "box" target is then the panel, not the section).
     *
     * @param  array<string, mixed>  $values
     */
    public static function resolve(array $values, bool $hasPanel): DesignOutput;
}
