<?php

namespace ScBudgetWeb\SiteBuilder\Blocks\Panel;

use Filament\Forms\Components\Field;

/**
 * Hover values in a block's panel: alongside Desktop, Tablet and Phone there's a Hover state
 * ($store.sbDevice.current === 'hover', switched at the top of the panel or on any size field). In it, each setting that
 * can change while the block is pointed at shows its hover value: size fields (DeviceValue, BoxSides) their "hover"
 * value, and a setting with a hover companion swaps to it. Settings without a hover value are dimmed (panel-tools).
 *
 * Only the editor changes: each value keeps its own saved key.
 */
final class HoverMode
{
    private const IS_HOVER = "\$store.sbDevice.current === 'hover'";

    /**
     * A setting's hover value: shown only in the Hover state.
     *
     * @template T of Field
     *
     * @param  T  $field
     * @return T
     */
    public static function only(Field $field): Field
    {
        return $field->extraFieldWrapperAttributes([
            'x-show' => self::IS_HOVER,
            'x-cloak' => '',
            'data-sb-hoverable' => 'true',
        ], merge: true);
    }

    /**
     * A setting whose hover value is another field: hidden in the Hover state, where its companion shows.
     *
     * @template T of Field
     *
     * @param  T  $field
     * @return T
     */
    public static function normal(Field $field): Field
    {
        return $field->extraFieldWrapperAttributes([
            'x-show' => '! ('.self::IS_HOVER.')',
            'data-sb-hoverable' => 'true',
        ], merge: true);
    }

    /**
     * A setting that holds its own hover value (a size field), so it isn't dimmed in the Hover state.
     *
     * @template T of Field
     *
     * @param  T  $field
     * @return T
     */
    public static function able(Field $field): Field
    {
        return $field->extraFieldWrapperAttributes(['data-sb-hoverable' => 'true'], merge: true);
    }
}
