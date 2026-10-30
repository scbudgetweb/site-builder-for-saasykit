<?php

namespace ScBudgetWeb\SiteBuilder\Support;

/**
 * Livewire refuses an update whose property path has more than livewire.payload.max_nesting_depth parts (10 by
 * default). The rich editor sends each change at its place in the editor's nested document, two parts per level, and a
 * block's panel adds its own (mountedActions.0.data.items.{key}.answer), so a bullet list in a panel's repeater is
 * already past 10. Filament's own advice is to raise the limit; this raises it to what the builder needs, and never
 * lowers it or lifts it entirely. Set site-builder.livewire_nesting_depth to false to leave Livewire's setting alone.
 */
class LivewireLimits
{
    /** Room for a list nested a few levels deep inside a table cell, in a repeater, in a block opened from a Section. */
    public const NESTING_DEPTH = 48;

    /** The most an app can ask for here: the limit guards against very deep payloads, so it stays bounded. */
    public const MAX_NESTING_DEPTH = 128;

    public static function apply(): void
    {
        $wanted = config('site-builder.livewire_nesting_depth', self::NESTING_DEPTH);
        $current = config('livewire.payload.max_nesting_depth');

        if ($wanted === false || $wanted === null || ! is_numeric($wanted) || $current === null) {
            return;
        }

        $wanted = min((int) $wanted, self::MAX_NESTING_DEPTH);

        if (! is_numeric($current) || (int) $current < $wanted) {
            config(['livewire.payload.max_nesting_depth' => $wanted]);
        }
    }
}
